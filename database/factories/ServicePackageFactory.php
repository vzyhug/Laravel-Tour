<?php

namespace Database\Factories;

use App\Models\ServicePackage;
use App\Models\ServiceResource;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePackageFactory extends Factory
{
    protected $model = ServicePackage::class;

    public function definition(): array
    {
        return ['resource_id' => ServiceResource::factory(), 'code' => fake()->unique()->uuid(), 'name' => 'Gói demo', 'price' => 500000, 'child_price' => 250000,
            'max_adults' => 2, 'max_children' => 1, 'occupancy' => 3, 'is_active' => true,
            'policy' => ['cancel_allowed' => true, 'cancel_before_hours' => 24, 'refund_percent' => 100],
            'attributes' => ['check_in_time' => '14:00', 'check_out_time' => '12:00', 'child_min_age' => 6, 'child_max_age' => 11, 'terms' => 'Điều kiện demo.',
                'slots' => ['08:00', '10:00'], 'duration_minutes' => 120, 'mode' => 'self_drive', 'deposit' => 2000000, 'min_driver_age' => 21, 'zones' => ['Hải Châu' => 0, 'Sơn Trà' => 50000], 'max_bags' => 2, 'airport' => 'DAD', 'meeting_point' => 'Sảnh đón demo']];
    }
}
