<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->uuid(), 'name' => 'Demo supplier', 'email' => fake()->safeEmail(), 'is_active' => true];
    }
}
