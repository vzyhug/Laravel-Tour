<?php

namespace Database\Factories;

use App\Models\ServiceInventoryDay;
use App\Models\ServiceResource;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceInventoryDayFactory extends Factory
{
    protected $model = ServiceInventoryDay::class;

    public function definition(): array
    {
        return ['resource_id' => ServiceResource::factory(), 'day' => today()->addDays(70), 'capacity' => 5, 'price_adjustment' => 0, 'is_closed' => false];
    }
}
