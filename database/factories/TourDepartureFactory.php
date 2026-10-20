<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Tour;
use App\Models\TourDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<TourDeparture> */
class TourDepartureFactory extends Factory
{
    protected $model = TourDeparture::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'departure_date' => fake()->dateTimeBetween('+7 days', '+90 days')->format('Y-m-d'),
            'return_date' => fn (array $a) => Carbon::parse($a['departure_date'])->addDays(Tour::findOrFail($a['tour_id'])->duration_days - 1)->toDateString(),
            'max_seats' => fake()->randomElement([16, 24, 30]),
            'available_seats' => fn (array $a) => $a['max_seats'],
            'adult_price' => fn (array $a) => Tour::findOrFail($a['tour_id'])->min_price,
            'child_price' => fn (array $a) => round($a['adult_price'] * 0.7),
            'guide_id' => Employee::factory()->guide(),
            'status' => 'open',
        ];
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'departure_date' => fake()->dateTimeBetween('-90 days', '-10 days')->format('Y-m-d'),
        ]);
    }

    public function full(): static
    {
        return $this->state(fn () => ['available_seats' => 0, 'status' => 'full']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }
}
