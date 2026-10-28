<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                DemoAccountSeeder::class,
                TourSeeder::class,
                TourItinerarySeeder::class,
                DestinationDirectorySeeder::class,
                HotelBookingCatalogSeeder::class,
                DestinationTourSeeder::class,
                DemoBookingSeeder::class,
            ]);
        });
    }
}
