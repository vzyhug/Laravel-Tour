<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use Illuminate\Database\Seeder;

/** Opt-in only: php artisan db:seed --class=HotelBookingDemoSeeder */
class HotelBookingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $destination = Destination::firstOrCreate(['slug' => 'hotel-booking-demo'], ['name' => 'Điểm đến DEMO', 'region' => 'Nam', 'description' => 'Dữ liệu minh họa đặt phòng, không phải địa điểm thật.', 'source_url' => 'https://example.com/demo', 'verified_on' => today()]);
        $hotel = Hotel::firstOrCreate(['slug' => 'vietgo-demo-hotel'], ['destination_id' => $destination->id, 'name' => 'VietGo DEMO Hotel (không phải khách sạn thật)', 'description' => 'Khách sạn giả lập phục vụ thử luồng đặt phòng. Giá, tồn và thanh toán đều là demo.', 'address' => 'Địa chỉ minh họa', 'website_url' => 'https://example.com/demo', 'source_url' => 'https://example.com/demo', 'verified_on' => today()]);
        $type = HotelRoomType::firstOrCreate(['hotel_id' => $hotel->id, 'name' => 'Phòng đôi DEMO'], ['max_guests' => 2, 'is_demo' => true]);
        for ($i = 0; $i < 30; $i++) {
            HotelRoomInventory::firstOrCreate(['room_type_id' => $type->id, 'stay_date' => today()->addDays($i)->toDateString()], ['total_rooms' => 3, 'available_rooms' => 3, 'nightly_price' => 500000]);
        }
        $this->command?->info('Đã thêm khách sạn giả lập DEMO; không thay đổi giá/tồn của khách sạn thật hoặc đè tồn đã dùng.');
    }
}
