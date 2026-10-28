<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use Illuminate\Database\Seeder;

class HotelBookingCatalogSeeder extends Seeder
{
    /**
     * Add a VietGo-managed reference offer for each catalog hotel that has no
     * room types yet. Rates and stock are reference values, not supplier feeds.
     */
    public function run(): void
    {
        $hotels = Hotel::with('destination')->orderBy('id')->get();

        foreach ($hotels as $hotel) {
            $room = HotelRoomType::firstOrCreate(
                ['hotel_id' => $hotel->id, 'name' => 'Phòng đôi tiêu chuẩn'],
                ['max_guests' => 2, 'is_active' => true, 'is_demo' => false],
            );

            $nightlyPrice = $this->referencePrice($hotel->destination->slug);

            for ($day = 0; $day < 90; $day++) {
                $stayDate = today()->addDays($day)->toDateString();
                HotelRoomInventory::firstOrCreate(
                    ['room_type_id' => $room->id, 'stay_date' => $stayDate],
                    ['total_rooms' => 2, 'available_rooms' => 2, 'nightly_price' => $nightlyPrice],
                );
            }
        }
    }

    private function referencePrice(string $destinationSlug): int
    {
        $premiumDestinations = ['ha-noi', 'ho-chi-minh', 'da-nang', 'phu-quoc', 'ha-long'];
        $popularDestinations = ['vung-tau', 'da-lat', 'nha-trang', 'sa-pa', 'hoi-an'];

        return match (true) {
            in_array($destinationSlug, $premiumDestinations, true) => 1_500_000,
            in_array($destinationSlug, $popularDestinations, true) => 1_200_000,
            default => 900_000,
        };
    }
}
