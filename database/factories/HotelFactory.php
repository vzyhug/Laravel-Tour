<?php

namespace Database\Factories;

use App\Models\Destination;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Hotel> */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    public function definition(): array
    {
        $data = json_decode(file_get_contents(database_path('data/destination-directory.json')), true, 512, JSON_THROW_ON_ERROR);
        $row = fake()->randomElement($data['hotels']);
        $destination = collect($data['destinations'])->firstWhere('slug', $row['destination']);

        return collect($row)->except('destination', 'image')->merge([
            'slug' => $row['slug'].'-'.Str::uuid(),
            'destination_id' => Destination::factory()->state(collect($destination)->except('place_slugs', 'slug')->all() + ['slug' => $destination['slug'].'-'.Str::uuid()]),
        ])->all();
    }
}
