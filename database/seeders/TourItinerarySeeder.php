<?php

namespace Database\Seeders;

use App\Models\ItineraryPlace;
use App\Models\Tour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TourItinerarySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/tour-itineraries.json')), true, 512, JSON_THROW_ON_ERROR);
        // Validate assets and all referenced days before writing anything.
        foreach ($data['places'] as $place) {
            $path = public_path(ltrim($place['image']['path'], '/'));
            if (! is_file($path) || hash_file('sha256', $path) !== $place['image']['sha256']) {
                throw new RuntimeException('Thiếu ảnh hoặc sai checksum: '.$place['name']);
            }
        }
        DB::transaction(function () use ($data) {
            $places = [];
            foreach ($data['places'] as $row) {
                $place = ItineraryPlace::updateOrCreate(['slug' => $row['slug']], collect($row)->except('slug', 'image')->all());
                $image = $row['image'];
                $place->images()->updateOrCreate(['path' => $image['path']], [
                    'sha256' => $image['sha256'],
                    'path' => $image['path'], 'caption' => $image['caption'], 'source_url' => $image['source_page'],
                    'author' => $image['author'], 'license' => $image['license'], 'license_url' => $image['license_url'] ?: null,
                ]);
                $places[$row['slug']] = $place->id;
            }
            foreach ($data['tours'] as $row) {
                $tour = Tour::where('title', $row['title'])->with('schedules')->first();
                if (! $tour) {
                    $this->command?->warn('Chưa có tour để ghép lịch: '.$row['title']);

                    continue;
                }
                if ((int) $tour->duration_days !== count($row['days'])) {
                    throw new RuntimeException('Số ngày đã thay đổi, cần rà lại lịch tour: '.$row['title']);
                }
                foreach ($row['days'] as $day) {
                    $schedule = $tour->schedules->firstWhere('day_number', $day['number']);
                    if (! $schedule) {
                        throw new RuntimeException('Thiếu ngày lịch gốc: '.$row['title'].' / '.$day['number']);
                    }
                    foreach ($day['places'] as $index => $slug) {
                        $schedule->stops()->updateOrCreate(['position' => $index + 1], ['place_id' => $places[$slug]]);
                    }
                }
            }
        });
    }
}
