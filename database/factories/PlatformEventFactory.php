<?php

namespace Database\Factories;

use App\Models\PlatformEvent;
use App\Models\PlatformOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformEventFactory extends Factory
{
    protected $model = PlatformEvent::class;

    public function definition(): array
    {
        return ['order_id' => PlatformOrder::factory(), 'type' => 'held', 'details' => ['source' => 'factory'], 'occurred_at' => now()];
    }
}
