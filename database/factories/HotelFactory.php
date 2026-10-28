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
        $google = $row['google_maps'] ?? [];

        return collect($row)->except('destination', 'image', 'google_maps')->merge([
            'slug' => $row['slug'].'-'.Str::uuid(),
            'destination_id' => Destination::factory()->state(collect($destination)->except('place_slugs', 'slug')->all() + ['slug' => $destination['slug'].'-'.Str::uuid()]),
        ])->merge([
            // Factory instances are test records with unique slugs, so don't claim the real hotel's Google identity.
            'google_place_id' => null,
            'google_data_id' => null,
            'google_rating' => $google['rating'] ?? null,
            'google_reviews' => $google['reviews'] ?? null,
            'latitude' => $google['latitude'] ?? null,
            'longitude' => $google['longitude'] ?? null,
            'google_maps_url' => null,
            'google_verified_on' => $google['checked_at'] ?? null,
        ])->all();
    }
}
