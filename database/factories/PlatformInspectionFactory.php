<?php

namespace Database\Factories;

use App\Models\PlatformInspection;
use App\Models\PlatformOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformInspectionFactory extends Factory
{
    protected $model = PlatformInspection::class;

    public function definition(): array
    {
        return ['order_id' => PlatformOrder::factory(), 'phase' => 'handover', 'odometer' => 10000, 'fuel_percent' => 100, 'condition_note' => 'Demo condition', 'deposit_amount' => 0, 'occurred_at' => now()];
    }
}
