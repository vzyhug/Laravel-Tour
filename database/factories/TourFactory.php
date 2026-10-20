<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tour> */
class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        $catalogue = json_decode(file_get_contents(database_path('data/tours.json')), true, 512, JSON_THROW_ON_ERROR);
        $destination = fake()->randomElement($catalogue);

        return [
            'title' => $destination['title'],
            'category_id' => fn () => TourCategory::firstOrCreate(['category_name' => $destination['category']])->getKey(),
            'province_id' => fn () => Province::firstOrCreate(['province_name' => $destination['province']], ['region' => $destination['region']])->getKey(),
            'duration_days' => count($destination['days']),
            'duration_nights' => fn (array $a) => max(0, $a['duration_days'] - 1),
            'pickup_point' => $destination['pickup_point'],
            'description' => $destination['description'],
            'itinerary_summary' => implode(' – ', array_column($destination['days'], 'title')),
            'included_services' => 'Xe đưa đón theo chương trình; lưu trú; bữa ăn được đánh dấu trong lịch trình; vé tham quan; hướng dẫn viên; bảo hiểm du lịch. Không gồm vé máy bay đến điểm đón và chi tiêu cá nhân.',
            'min_price' => $destination['min_price'],
            'is_active' => true,
            'created_by' => Employee::factory()->admin(),
        ];
    }
}
