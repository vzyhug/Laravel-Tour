<?php

namespace App\Console\Commands;

use App\Models\ItineraryPlaceImage;
use App\Models\Tour;
use Illuminate\Console\Command;

class AuditTourItineraries extends Command
{
    protected $signature = 'itineraries:audit {--json}';

    protected $description = 'Check every tour day, place link and attributed local photograph';

    public function handle(): int
    {
        $rows = [];
        $problems = [];
        foreach (Tour::with('schedules.stops.place.images')->orderBy('tour_id')->get() as $tour) {
            $stops = $tour->schedules->flatMap->stops;
            $missingDays = $tour->schedules->filter(fn ($day) => $day->stops->isEmpty())->pluck('day_number')->all();
            if ($missingDays || $tour->schedules->count() !== (int) $tour->duration_days) {
                $problems[] = ['tour_id' => $tour->tour_id, 'missing_days' => $missingDays, 'duration' => $tour->duration_days, 'schedule_days' => $tour->schedules->count()];
            }
            foreach ($stops as $stop) {
                if (! $stop->place || $stop->place->images->isEmpty()) {
                    $problems[] = ['stop_id' => $stop->id, 'problem' => 'missing_place_or_image'];
                }
            }
            $rows[] = ['tour_id' => $tour->tour_id, 'title' => $tour->title, 'days' => $tour->schedules->count(), 'stops' => $stops->count()];
        }
        foreach (ItineraryPlaceImage::all() as $image) {
            $path = public_path(ltrim($image->path, '/'));
            if (! is_file($path) || hash_file('sha256', $path) !== $image->sha256 || ! $image->author || ! $image->source_url || ! $image->license) {
                $problems[] = ['image_id' => $image->id, 'problem' => 'missing_or_changed_photo_or_attribution'];
            }
        }
        $report = ['tours' => count($rows), 'days' => array_sum(array_column($rows, 'days')), 'stops' => array_sum(array_column($rows, 'stops')),
            'images' => ItineraryPlaceImage::count(), 'problems' => $problems, 'rows' => $rows];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->table(['ID', 'Tour', 'Days', 'Stops'], $rows);
            $this->line('Images: '.$report['images'].'; problems: '.count($problems));
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }
}
