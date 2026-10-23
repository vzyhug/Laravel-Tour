<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\VehicleInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleInspectionFactory extends Factory
{
    protected $model = VehicleInspection::class;

    public function definition(): array
    {
        return ['order_id' => ServiceBooking::factory(), 'phase' => 'handover', 'odometer' => 10000, 'fuel_percent' => 100, 'condition_note' => 'Demo condition', 'deposit_amount' => 0, 'occurred_at' => now()];
    }
}
