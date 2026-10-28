<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Models\TourImage;
use App\Models\TourSchedule;
use App\Services\Media\MediaCatalog;
use Illuminate\Database\Seeder;
use RuntimeException;

class TourSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = json_decode(file_get_contents(database_path('data/tours.json')), true, 512, JSON_THROW_ON_ERROR);
        $images = collect(json_decode(file_get_contents(database_path('data/tour-images.json')), true, 512, JSON_THROW_ON_ERROR));
        $catalog = app(MediaCatalog::class);
        $references = $images->mapWithKeys(fn ($image) => [$image['path'] => $catalog->reference($image)]);

        $admin = Employee::where('username', 'admin')->firstOrFail();
        $guideNames = ['guide', 'guide_tuan', 'guide_huong', 'guide_long', 'guide_phuong', 'guide_hai', 'guide_mai', 'guide_bao'];
        $guides = Employee::whereIn('username', $guideNames)
            ->whereHas('role', fn ($q) => $q->where('role_name', 'Guide'))->get()->keyBy('username');
        if ($guides->count() !== count($guideNames)) {
            throw new RuntimeException('Hãy chạy DemoAccountSeeder trước TourSeeder.');
        }

        foreach ($catalogue as $index => $data) {
            $province = Province::firstOrCreate(['province_name' => $data['province']], ['region' => $data['region']]);
            $category = TourCategory::firstOrCreate(['category_name' => $data['category']], ['description' => 'Khám phá thiên nhiên, văn hóa và trải nghiệm địa phương.']);
            $tour = Tour::updateOrCreate(['title' => $data['title']], Tour::factory()->raw([
                'title' => $data['title'],
                'province_id' => $province->getKey(),
                'category_id' => $category->getKey(),
                'duration_days' => count($data['days']),
                'duration_nights' => count($data['days']) - 1,
                'pickup_point' => $data['pickup_point'],
                'description' => $data['description'],
                'itinerary_summary' => implode(' – ', array_column($data['days'], 'title')),
                'min_price' => $data['min_price'],
                'created_by' => $admin->getKey(),
            ]));

            $tourImages = $images->where('destination', $data['slug'])->values();
            if ($tourImages->count() < 2) {
                throw new RuntimeException('Cần ít nhất hai ảnh cho '.$data['slug']);
            }
            $tour->images()->where('is_thumbnail', true)->update(['is_thumbnail' => false]);
            foreach ($tourImages as $imageIndex => $image) {
                $record = TourImage::where('tour_id', $tour->getKey())
                    ->where(fn ($query) => $query->where('sha256', $image['sha256'])->orWhere('img_url', $image['path']))->first();
                $record ??= new TourImage(['tour_id' => $tour->getKey()]);
                $record->fill(array_merge($references[$image['path']]->columns(), ['is_thumbnail' => $imageIndex === 0]))->save();
            }

            foreach ($data['days'] as $dayIndex => $day) {
                TourSchedule::updateOrCreate([
                    'tour_id' => $tour->getKey(),
                    'day_number' => $dayIndex + 1,
                ], TourSchedule::factory()->raw([
                    'tour_id' => $tour->getKey(),
                    'day_number' => $dayIndex + 1,
                    'title' => $day['title'],
                    'activities' => $day['activities'],
                    'has_breakfast' => $dayIndex > 0,
                    'has_dinner' => $dayIndex < count($data['days']) - 1,
                ]));
            }

            // Four named guides cover each block of 16 tours. The original tours keep their guides and dates.
            $localIndex = $index % 16;
            $guideName = $guideNames[intdiv($index, 16) * 4 + $index % 4];
            // Dates move forward on a later day's seed; existing departures and bookings remain intact.
            foreach ([-30, 7, 37, 67] as $offset) {
                $date = today()->addDays($offset + intdiv($localIndex, 4) * 5 + $localIndex % 4);
                TourDeparture::query()
                    ->where('tour_id', $tour->getKey())
                    ->whereDate('departure_date', $date->toDateString())
                    ->firstOrCreate([], fn () => TourDeparture::factory()->raw([
                        'tour_id' => $tour->getKey(),
                        'departure_date' => $date->toDateString(),
                        'max_seats' => 24,
                        'guide_id' => $guides[$guideName]->getKey(),
                    ]));
            }
        }
    }
}
