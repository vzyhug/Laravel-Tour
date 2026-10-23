<?php

namespace Database\Factories;

use App\Models\BusTrip;
use App\Models\ServiceResource;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusTripFactory extends Factory
{
    protected $model = BusTrip::class;

    public function definition(): array
    {
        return ['resource_id' => ServiceResource::factory()->bus(), 'code' => fake()->unique()->uuid(), 'starts_at' => today('Asia/Ho_Chi_Minh')->addDays(5)->setTime(18, 0)->utc(), 'ends_at' => today('Asia/Ho_Chi_Minh')->addDays(6)->setTime(0, 0)->utc(), 'is_active' => true];
    }
}
