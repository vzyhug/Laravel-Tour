<?php

namespace Database\Factories;

use App\Models\ItineraryPlace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ItineraryPlace> */
class ItineraryPlaceFactory extends Factory
{
    protected $model = ItineraryPlace::class;

    public function definition(): array
    {
        $data = json_decode(file_get_contents(database_path('data/destination-directory.json')), true, 512, JSON_THROW_ON_ERROR);
        $row = fake()->randomElement($data['places']);

        return collect($row)->except('image')->merge(['slug' => $row['slug'].'-'.Str::uuid()])->all();
    }
}
