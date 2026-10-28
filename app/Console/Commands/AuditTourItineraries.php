<?php

namespace App\Console\Commands;

use App\Models\ItineraryPlaceImage;
use App\Models\Tour;
use App\Services\Media\ImageStorage;
use App\Services\Media\StoredImage;
use App\Services\Media\SupabaseImageStorage;
use Illuminate\Console\Command;

class AuditTourItineraries extends Command
{
    protected $signature = 'itineraries:audit {--json} {--verify-cloud : Download cloud files and check SHA-256}';

    protected $description = 'Check every tour day, place link and attributed photograph';

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
            try {
                if (! $image->author || ! $image->source_url || ! $image->license) {
                    throw new \RuntimeException('missing_attribution');
                }
                if ($image->storage_disk === 'supabase') {
                    $reference = new StoredImage('supabase', $image->storage_bucket, $image->storage_path, $image->path, $image->sha256);
                    if ($image->path !== SupabaseImageStorage::publicUrl($reference->path) || ! $reference->bucket || ! $reference->path) {
                        throw new \RuntimeException('invalid_cloud_reference');
                    }
                    if ($this->option('verify-cloud')) {
                        app(ImageStorage::class)->verify($reference);
                    }
                } else {
                    $path = public_path(ltrim($image->path, '/'));
                    if (! is_file($path) || hash_file('sha256', $path) !== $image->sha256) {
                        throw new \RuntimeException('missing_or_changed_local_photo');
                    }
                }
            } catch (\Throwable $exception) {
                $problems[] = ['image_id' => $image->id, 'problem' => $exception->getMessage()];
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
