<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\Customer;
use App\Models\TourDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'booking_code' => 'DEMO-'.Str::upper(Str::random(12)),
            'customer_id' => Customer::factory(),
            'departure_id' => TourDeparture::factory(),
            'booking_date' => fn (array $a) => TourDeparture::findOrFail($a['departure_id'])->departure_date->copy()->subDays(5)->min(now()),
            'total_adults' => fake()->numberBetween(1, 3),
            'total_children' => 0,
            'total_amount' => function (array $a) {
                $departure = TourDeparture::findOrFail($a['departure_id']);

                return $a['total_adults'] * $departure->adult_price;
            },
            'status' => 'pending',
            'created_by_employee' => null,
        ];
    }

    public function withPassengers(): static
    {
        return $this->afterCreating(function (Booking $booking) {
            BookingPassenger::factory()->count($booking->total_adults)->create([
                'booking_id' => $booking->getKey(),
                'passenger_type' => 'adult',
            ]);
        });
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'departure_id' => TourDeparture::factory()->past()]);
    }
}
