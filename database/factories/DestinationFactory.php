<?php

namespace Database\Factories;

use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Destination> */
class DestinationFactory extends Factory
{
    protected $model = Destination::class;

    public function definition(): array
    {
        $data = json_decode(file_get_contents(database_path('data/destination-directory.json')), true, 512, JSON_THROW_ON_ERROR);
        $row = fake()->randomElement($data['destinations']);

        return collect($row)->except('place_slugs')->merge(['slug' => $row['slug'].'-'.Str::uuid()])->all();
    }
}
