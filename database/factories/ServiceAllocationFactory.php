<?php

namespace Database\Factories;

use App\Models\ServiceAllocation;
use App\Models\ServiceBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceAllocationFactory extends Factory
{
    protected $model = ServiceAllocation::class;

    public function definition(): array
    {
        return ['order_id' => ServiceBooking::factory(), 'resource_id' => fn ($a) => ServiceBooking::findOrFail($a['order_id'])->option->resource_id, 'starts_at' => today()->addDays(40), 'ends_at' => today()->addDays(41), 'quantity' => 1];
    }
}
