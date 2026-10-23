<?php

namespace Database\Factories;

use App\Models\PlatformInventoryDay;
use App\Models\PlatformResource;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformInventoryDayFactory extends Factory
{
    protected $model = PlatformInventoryDay::class;

    public function definition(): array
    {
        return ['resource_id' => PlatformResource::factory(), 'day' => today()->addDays(70), 'capacity' => 5, 'price_adjustment' => 0, 'is_closed' => false];
    }
}
