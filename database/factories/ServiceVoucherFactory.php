<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\ServiceVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceVoucherFactory extends Factory
{
    protected $model = ServiceVoucher::class;

    public function definition(): array
    {
        return ['order_id' => ServiceBooking::factory(), 'code' => fake()->unique()->uuid(), 'remaining_uses' => 1, 'status' => 'issued', 'valid_from' => today()->addDays(5), 'valid_until' => today()->addDays(7)];
    }
}
