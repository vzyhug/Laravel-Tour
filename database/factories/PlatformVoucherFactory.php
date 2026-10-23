<?php

namespace Database\Factories;

use App\Models\PlatformOrder;
use App\Models\PlatformVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformVoucherFactory extends Factory
{
    protected $model = PlatformVoucher::class;

    public function definition(): array
    {
        return ['order_id' => PlatformOrder::factory(), 'code' => fake()->unique()->uuid(), 'remaining_uses' => 1, 'status' => 'issued', 'valid_from' => today()->addDays(5), 'valid_until' => today()->addDays(7)];
    }
}
