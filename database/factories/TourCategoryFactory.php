<?php

namespace Database\Factories;

use App\Models\TourCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourCategory> */
class TourCategoryFactory extends Factory
{
    protected $model = TourCategory::class;

    public function definition(): array
    {
        return [
            'category_name' => 'Tour trải nghiệm '.fake()->unique()->numerify('######'),
            'description' => 'Khám phá thiên nhiên và văn hóa địa phương.',
        ];
    }
}
