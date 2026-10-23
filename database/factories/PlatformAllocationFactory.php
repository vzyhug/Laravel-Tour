<?php

namespace Database\Factories;

use App\Models\PlatformAllocation;
use App\Models\PlatformOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformAllocationFactory extends Factory
{
    protected $model = PlatformAllocation::class;

    public function definition(): array
    {
        return ['order_id' => PlatformOrder::factory(), 'resource_id' => fn ($a) => PlatformOrder::findOrFail($a['order_id'])->option->resource_id, 'starts_at' => today()->addDays(40), 'ends_at' => today()->addDays(41), 'quantity' => 1];
    }
}
