<?php

namespace Database\Factories;

use App\Models\PlatformSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformSupplierFactory extends Factory
{
    protected $model = PlatformSupplier::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->uuid(), 'name' => 'Demo supplier', 'email' => fake()->safeEmail(), 'is_active' => true];
    }
}
