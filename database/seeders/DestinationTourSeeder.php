<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ItineraryPlace;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Services\Media\MediaCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DestinationTourSeeder extends Seeder
{
    public function run(): void
    {
        $programs = json_decode(file_get_contents(database_path('data/destination-tours.json')), true, 512, JSON_THROW_ON_ERROR)['tours'];
        $catalog = app(MediaCatalog::class);
        $images = collect($catalog->images())->keyBy('path');
        $references = collect($programs)->pluck('image_paths')->flatten()->unique()->mapWithKeys(function ($path) use ($catalog, $images) {
            $image = $images->get($path) ?? throw new RuntimeException('Thiếu ảnh trong danh mục: '.$path);

            return [$path => $catalog->reference($image)];
        });
        $slugs = collect($programs)->pluck('days')->flatten(1)->pluck('places')->flatten()->unique()->values();
        $places = ItineraryPlace::with('images')->whereIn('slug', $slugs)->get()->keyBy('slug');
        if ($places->count() !== $slugs->count() || $places->contains(fn ($place) => $place->images->isEmpty())) {
            throw new RuntimeException('Chạy DestinationDirectorySeeder trước để có đủ địa danh và ảnh Vũng Tàu.');
        }
        $admin = Employee::where('username', 'admin')->firstOrFail();
        $guides = Employee::whereHas('role', fn ($query) => $query->where('role_name', 'Guide'))->orderBy('employee_id')->get();
        if ($guides->isEmpty()) {
            throw new RuntimeException('Cần tài khoản hướng dẫn viên trước khi tạo lịch khởi hành.');
        }

        DB::transaction(function () use ($programs, $references, $places, $admin, $guides) {
            foreach ($programs as $index => $program) {
                $tour = Tour::updateOrCreate(['title' => $program['title']], Tour::factory()->destinationProgram($program['slug'])->raw(['created_by' => $admin->getKey()]));
                foreach ($program['image_paths'] as $position => $path) {
                    $reference = $references[$path];
                    $image = $tour->images()->firstOrNew(['sha256' => $reference->sha256]);
                    if (! $image->exists) {
                        $image->is_thumbnail = $position === 0 && ! $tour->images()->where('is_thumbnail', true)->exists();
                    }
                    $image->fill($reference->columns())->save();
                }
                foreach ($program['days'] as $dayIndex => $day) {
                    $schedule = $tour->schedules()->updateOrCreate(['day_number' => $dayIndex + 1], [
                        'title' => $day['title'], 'activities' => $day['activities'],
                        'has_breakfast' => $day['has_breakfast'], 'has_lunch' => $day['has_lunch'], 'has_dinner' => $day['has_dinner'],
                    ]);
                    foreach ($day['places'] as $position => $slug) {
                        $schedule->stops()->updateOrCreate(['position' => $position + 1], ['place_id' => $places[$slug]->getKey()]);
                    }
                }
                foreach ([10, 40, 70] as $offset) {
                    $this->departure($tour, today()->addDays($offset + $index * 7), $guides);
                }
            }
        });
    }

    private function departure(Tour $tour, Carbon $preferred, Collection $guides): void
    {
        // Preserve existing departures, including seats, prices and cancellations on a rerun.
        if ($tour->departures()->whereBetween('departure_date', [$preferred->toDateString(), $preferred->copy()->addDays(14)->toDateString()])->exists()) {
            return;
        }
        for ($shift = 0; $shift <= 14; $shift++) {
            $start = $preferred->copy()->addDays($shift);
            $end = $start->copy()->addDays($tour->duration_days - 1);
            $busy = TourDeparture::where('status', '!=', 'cancelled')
                ->whereDate('departure_date', '<=', $end->toDateString())->whereDate('return_date', '>=', $start->toDateString())->pluck('guide_id');
            $guide = $guides->first(fn ($guide) => ! $busy->contains($guide->getKey()));
            if ($guide) {
                TourDeparture::create(TourDeparture::factory()->raw([
                    'tour_id' => $tour->getKey(), 'guide_id' => $guide->getKey(), 'departure_date' => $start->toDateString(),
                    'departure_time' => '07:00:00', 'max_seats' => 24,
                ]));

                return;
            }
        }
        throw new RuntimeException('Không có hướng dẫn viên rảnh trong khung ngày của '.$tour->title);
    }
}
