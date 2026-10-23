<?php

namespace Database\Seeders;

use App\Models\PlatformInventoryDay;
use App\Models\PlatformProduct;
use App\Models\PlatformSupplier;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PlatformCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = PlatformSupplier::firstOrCreate(['code' => 'VIETGO-DEMO'], ['name' => 'VietGo Demo Services (dữ liệu giả)', 'email' => 'partner@vietgo.test', 'is_active' => true]);
        $catalogue = [
            ['stay', 'stay-da-nang', 'VietGo Stay Đà Nẵng — demo', 'Đà Nẵng · Hải Châu', 'da-nang-2.jpg'],
            ['stay', 'stay-hoi-an', 'VietGo Stay Hội An — demo', 'Hội An · Cẩm Phô', 'hoi-an-1.jpg'],
            ['stay', 'stay-ha-noi', 'VietGo Stay Hà Nội — demo', 'Hà Nội · Hoàn Kiếm', 'ha-noi-1.jpg'],
            ['bus', 'bus-danang-hue', 'VietGo Bus Đà Nẵng – Huế – Quảng Trị — demo', 'Đà Nẵng · Huế · Quảng Trị', 'hue-1.jpg'],
            ['bus', 'bus-hanoi-thanhhoa', 'VietGo Bus Hà Nội – Ninh Bình – Thanh Hóa — demo', 'Hà Nội · Ninh Bình · Thanh Hóa', 'ninh-binh-1.jpg'],
            ['transfer', 'transfer-dad', 'Đưa đón sân bay Đà Nẵng — demo', 'Đà Nẵng · Sân bay DAD', 'da-nang-1.jpg'],
            ['transfer', 'transfer-han', 'Đưa đón sân bay Nội Bài — demo', 'Hà Nội · Sân bay HAN', 'ha-noi-2.jpg'],
            ['rental', 'rental-sedan-dn', 'VietGo Sedan Đà Nẵng — demo', 'Đà Nẵng · Hải Châu', 'da-nang-2.jpg'],
            ['rental', 'rental-mpv-dn', 'VietGo MPV Đà Nẵng — demo', 'Đà Nẵng · Sơn Trà', 'da-nang-1.jpg'],
            ['rental', 'rental-suv-hn', 'VietGo SUV Hà Nội — demo', 'Hà Nội · Cầu Giấy', 'ha-noi-1.jpg'],
            ['activity', 'activity-hoian', 'Dạo phố & trải nghiệm Hội An — demo', 'Hội An · Phố cổ', 'hoi-an-1.jpg'],
            ['activity', 'activity-danang', 'Vườn trải nghiệm Đà Nẵng — demo', 'Đà Nẵng · Sơn Trà', 'da-nang-2.jpg'],
            ['activity', 'activity-hanoi', 'Không gian văn hóa Hà Nội — demo', 'Hà Nội · Hoàn Kiếm', 'ha-noi-2.jpg'],
        ];
        foreach ($catalogue as [$type, $slug, $name, $location, $image]) {
            $path = 'images/tours/demo/'.$image;
            if (! is_file(public_path($path))) {
                $path = 'images/tours/demo/da-nang-2.jpg';
            }
            $product = PlatformProduct::firstOrCreate(['slug' => $slug], [
                'supplier_id' => $supplier->id, 'type' => $type, 'name' => $name, 'location' => $location,
                'description' => 'Dữ liệu giả dành cho đồ án VietGo. Ảnh địa danh chỉ minh họa; không phải hình ảnh phòng/xe của một nhà cung cấp thật. Chọn ngày, gói và nhóm khách để kiểm tra giá và tồn thực trong database demo.',
                'image_path' => $path, 'attributes' => ['demo' => true, 'stars' => $type === 'stay' ? 3 : 0, 'amenities' => $type === 'stay' ? ['Wi-Fi demo', 'Lễ tân', 'Phòng không hút thuốc'] : ['Chứng từ điện tử demo', 'Điều kiện giá rõ ràng']], 'is_active' => true,
            ]);
            if ($type === 'stay') {
                foreach ([['Phòng tiêu chuẩn', 8, 2, 1, 3, 650000], ['Phòng gia đình', 4, 4, 2, 5, 1100000]] as [$room, $capacity, $adults, $children, $occupancy, $price]) {
                    $resource = $this->resource($product, $room, 'room_pool', $capacity);
                    foreach ([false, true] as $breakfast) {
                        $code = strtoupper($slug).'-'.$resource->id.'-'.($breakfast ? 'BF' : 'ROOM');
                        $this->option($resource, $code, $room.($breakfast ? ' · Bữa sáng' : ' · Chỉ phòng'), $price + ($breakfast ? 150000 : 0), [
                            'max_adults' => $adults, 'max_children' => $children, 'occupancy' => $occupancy,
                            'attributes' => ['breakfast' => $breakfast, 'check_in_time' => '14:00', 'check_out_time' => '12:00', 'child_min_age' => 0, 'child_max_age' => 11,
                                'terms' => 'Mỗi phòng cần ít nhất một người lớn. Không có giường phụ ngoài sức chứa công bố. Giá tính từng đêm; yêu cầu đặc biệt chưa được xác nhận.'],
                        ], $breakfast ? 80 : 100);
                    }
                }
            } elseif ($type === 'bus') {
                $resource = $this->resource($product, 'Xe ghế ngồi 8 chỗ demo', 'bus', 8, 0, ['seats' => ['A1', 'A2', 'A3', 'A4', 'B1', 'B2', 'B3', 'B4']]);
                $this->option($resource, strtoupper($slug).'-SEAT', 'Ghế ngồi · tính theo đoạn tuyến', 120000, ['occupancy' => 8, 'attributes' => ['terms' => 'Mỗi ghế được giữ theo đoạn lên/xuống đã chọn. Có thể bán cùng ghế trên hai đoạn không giao nhau. Đến điểm đón trước giờ ghi trên chứng từ.']], 70);
                $names = $slug === 'bus-danang-hue' ? ['Đà Nẵng', 'Huế', 'Quảng Trị'] : ['Hà Nội', 'Ninh Bình', 'Thanh Hóa'];
                for ($i = 1; $i <= 28; $i++) {
                    $start = Carbon::today('Asia/Ho_Chi_Minh')->addDays($i)->setTime(18, 0)->utc();
                    $trip = $resource->trips()->firstOrCreate(['code' => strtoupper($slug).'-'.$start->copy()->timezone('Asia/Ho_Chi_Minh')->format('Ymd')], ['starts_at' => $start, 'ends_at' => $start->copy()->addHours(6), 'is_active' => true]);
                    foreach ($names as $position => $station) {
                        $trip->stops()->firstOrCreate(['position' => $position], ['name' => $station, 'address' => 'Điểm đón demo VietGo tại '.$station, 'time_at' => $start->copy()->addHours($position * 3)]);
                    }
                }
            } elseif ($type === 'transfer') {
                $zones = $slug === 'transfer-dad' ? ['Hải Châu' => 0, 'Sơn Trà' => 50000, 'Ngũ Hành Sơn' => 80000] : ['Hoàn Kiếm' => 50000, 'Cầu Giấy' => 0, 'Tây Hồ' => 30000];
                foreach ([['Sedan', 3, 4, 2, 300000], ['MPV', 2, 7, 4, 450000]] as [$vehicle, $capacity, $occupancy, $bags, $price]) {
                    $resource = $this->resource($product, 'Quỹ xe '.$vehicle, 'vehicle_pool', $capacity, 30);
                    $this->option($resource, strtoupper($slug).'-'.$vehicle, $vehicle.' · Xe riêng', $price, ['occupancy' => $occupancy, 'attributes' => [
                        'zones' => $zones, 'airport' => $slug === 'transfer-dad' ? 'Sân bay Đà Nẵng (DAD)' : 'Sân bay Nội Bài (HAN)', 'max_bags' => $bags,
                        'duration_minutes' => 90, 'meeting_point' => 'Sảnh đón demo; giờ và vị trí cụ thể theo xác nhận điều phối', 'terms' => 'Chỉ phục vụ trong vùng đã chọn. Chưa có geocoding hoặc theo dõi chuyến bay thật. Chuyến demo đã gồm phí vùng; phát sinh ngoài gói cần thỏa thuận, không tự thu.'],
                    ]);
                }
            } elseif ($type === 'rental') {
                $resource = $this->resource($product, 'Xe demo '.strtoupper($slug), 'vehicle', 1, 60);
                foreach (['self_drive', 'with_driver'] as $mode) {
                    $this->option($resource, strtoupper($slug).'-'.$mode, $mode === 'self_drive' ? 'Thuê tự lái' : 'Thuê có tài xế', $mode === 'self_drive' ? 800000 : 1200000, [
                        'occupancy' => str_contains($slug, 'sedan') ? 4 : 7, 'attributes' => ['mode' => $mode, 'deposit' => $mode === 'self_drive' ? 5000000 : 0, 'min_driver_age' => 21,
                            'terms' => 'Mỗi ngày thuê là block 24 giờ, phần lẻ làm tròn lên. Tự lái cần đủ 21 tuổi, giấy phép phù hợp và cọc demo khi nhận xe. Gói có tài xế có định mức 200 km/ngày; phát sinh cần xác nhận riêng. Kiểm tra xe khi bàn giao/trả; không tự ghi nhận tiền cọc là doanh thu thuê.'],
                    ], 80);
                }
            } else {
                $resource = $this->resource($product, 'Quỹ lượt theo khung giờ', 'ticket_pool', 40);
                foreach ([['STANDARD', 'Vé tham quan', 180000], ['GUIDED', 'Vé có trải nghiệm hướng dẫn', 280000]] as [$code, $name, $price]) {
                    $this->option($resource, strtoupper($slug).'-'.$code, $name, $price, ['child_price' => intdiv($price, 2), 'occupancy' => 20,
                        'attributes' => ['slots' => ['08:00', '10:00', '14:00'], 'duration_minutes' => 120, 'child_min_age' => 6, 'child_max_age' => 11,
                            'terms' => 'Chỉ có hiệu lực cho ngày/khung giờ đã chọn. Vé trẻ em từ 6 đến 11 tuổi tại ngày sử dụng; nhóm tuổi khác cần chọn gói phù hợp. Một đơn có lượt theo số người; không phải combo nhiều địa điểm.'],
                    ], $code === 'STANDARD' ? 0 : 100);
                }
            }
        }
    }

    private function resource(PlatformProduct $product, string $name, string $kind, int $capacity, int $buffer = 0, array $attributes = [])
    {
        $resource = $product->resources()->firstOrCreate(['name' => $name], ['kind' => $kind, 'capacity' => $capacity, 'buffer_minutes' => $buffer, 'attributes' => $attributes, 'is_active' => true]);
        $calendar = [];
        for ($i = 0; $i <= 90; $i++) {
            $day = Carbon::today('Asia/Ho_Chi_Minh')->addDays($i);
            $calendar[] = ['resource_id' => $resource->id, 'day' => $day->toDateString(), 'capacity' => $capacity, 'price_adjustment' => $kind === 'room_pool' && $day->isWeekend() ? 100000 : 0, 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()];
        }
        PlatformInventoryDay::insertOrIgnore($calendar);

        return $resource;
    }

    private function option($resource, string $code, string $name, int $price, array $overrides = [], int $refund = 100)
    {
        $base = ['name' => $name, 'price' => $price, 'child_price' => null, 'max_adults' => 2, 'max_children' => 0, 'occupancy' => 2,
            'policy' => ['cancel_allowed' => true, 'cancel_before_hours' => 24, 'refund_percent' => $refund], 'attributes' => [], 'is_active' => true];

        return $resource->options()->firstOrCreate(['code' => $code], array_replace($base, $overrides));
    }
}
