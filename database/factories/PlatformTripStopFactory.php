<?php

namespace Database\Factories;

use App\Models\PlatformTrip;
use App\Models\PlatformTripStop;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformTripStopFactory extends Factory
{
    protected $model = PlatformTripStop::class;

    public function definition(): array
    {
        return ['trip_id' => PlatformTrip::factory(), 'position' => 0, 'name' => 'Demo station', 'address' => 'Demo address', 'time_at' => fn ($a) => PlatformTrip::findOrFail($a['trip_id'])->starts_at];
    }
}
