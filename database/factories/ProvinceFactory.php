<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Province> */
class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    public function definition(): array
    {
        return [
            'province_name' => fake('vi_VN')->unique()->city(),
            'region' => fake()->randomElement(['Bắc', 'Trung', 'Nam']),
        ];
    }
}
