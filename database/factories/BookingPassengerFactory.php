<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPassenger;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingPassenger> */
class BookingPassengerFactory extends Factory
{
    protected $model = BookingPassenger::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'full_name' => fake('vi_VN')->name(),
            'passenger_type' => 'adult',
            'id_card' => fake()->numerify('############'),
            'date_of_birth' => fake()->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
        ];
    }

}
