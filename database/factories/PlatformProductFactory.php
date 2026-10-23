<?php

namespace Database\Factories;

use App\Models\PlatformProduct;
use App\Models\PlatformSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformProductFactory extends Factory
{
    protected $model = PlatformProduct::class;

    public function definition(): array
    {
        return ['supplier_id' => PlatformSupplier::factory(), 'type' => 'stay', 'slug' => fake()->unique()->slug().'-'.fake()->uuid(),
            'name' => 'VietGo Demo '.fake()->city(), 'location' => 'Đà Nẵng', 'description' => 'Dữ liệu giả phục vụ kiểm thử dịch vụ VietGo.',
            'image_path' => 'images/tours/demo/da-nang-2.jpg', 'attributes' => ['demo' => true, 'stars' => 3, 'amenities' => ['Wi-Fi demo']], 'is_active' => true];
    }

    public function bus(): static
    {
        return $this->state(['type' => 'bus']);
    }

    public function rental(): static
    {
        return $this->state(['type' => 'rental']);
    }

    public function transfer(): static
    {
        return $this->state(['type' => 'transfer']);
    }

    public function activity(): static
    {
        return $this->state(['type' => 'activity']);
    }
}
