<?php

namespace Database\Factories;

use App\Models\BusTrip;
use App\Models\BusTripStop;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusTripStopFactory extends Factory
{
    protected $model = BusTripStop::class;

    public function definition(): array
    {
        return ['trip_id' => BusTrip::factory(), 'position' => 0, 'name' => 'Demo station', 'address' => 'Demo address', 'time_at' => fn ($a) => BusTrip::findOrFail($a['trip_id'])->starts_at];
    }
}
