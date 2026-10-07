<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Province;
use App\Models\Role;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::create(['role_name' => 'Admin', 'description' => 'Quản trị hệ thống']);
        Role::create(['role_name' => 'Sale', 'description' => 'Tư vấn và bán tour']);
        $guideRole = Role::create(['role_name' => 'Guide', 'description' => 'Hướng dẫn viên']);

        $admin = Employee::create(['username' => 'admin', 'password_hash' => Hash::make('Admin@123'), 'full_name' => 'Quản trị VietGo', 'email' => 'admin@vietgo.test', 'phone' => '0900000001', 'role_id' => $adminRole->getKey(), 'is_active' => true]);
        $guide = Employee::create(['username' => 'guide', 'password_hash' => Hash::make('Guide@123'), 'full_name' => 'Nguyễn Minh Anh', 'email' => 'guide@vietgo.test', 'phone' => '0900000002', 'role_id' => $guideRole->getKey(), 'is_active' => true]);

        $categories = collect([
            ['category_name' => 'Tour Biển', 'description' => 'Nghỉ dưỡng và khám phá biển đảo'],
            ['category_name' => 'Tour Núi', 'description' => 'Trekking và khám phá thiên nhiên'],
            ['category_name' => 'Tour Văn hóa', 'description' => 'Di sản, lịch sử và ẩm thực'],
        ])->map(fn (array $data) => TourCategory::create($data));

        $provinces = collect([
            ['province_name' => 'Quảng Ninh', 'region' => 'Bắc'],
            ['province_name' => 'Lào Cai', 'region' => 'Bắc'],
            ['province_name' => 'Đà Nẵng', 'region' => 'Trung'],
            ['province_name' => 'Lâm Đồng', 'region' => 'Trung'],
            ['province_name' => 'Kiên Giang', 'region' => 'Nam'],
        ])->map(fn (array $data) => Province::create($data));

        $tourData = [
            ['Khám phá Vịnh Hạ Long', 0, 0, 2, 1, 'Hà Nội', 2500000],
            ['Sapa – Thành phố trong sương', 1, 1, 3, 2, 'Hà Nội', 3200000],
            ['Đà Nẵng – Hội An – Bà Nà Hills', 2, 2, 4, 3, 'Sân bay Đà Nẵng', 4500000],
            ['Đà Lạt – Thành phố ngàn hoa', 2, 3, 3, 2, 'Sân bay Liên Khương', 3800000],
            ['Phú Quốc – Thiên đường biển đảo', 0, 4, 4, 3, 'Sân bay Phú Quốc', 5200000],
        ];

        foreach ($tourData as $index => [$title, $category, $province, $days, $nights, $pickup, $price]) {
            $tour = Tour::create([
                'title' => $title,
                'category_id' => $categories[$category]->getKey(),
                'province_id' => $provinces[$province]->getKey(),
                'duration_days' => $days,
                'duration_nights' => $nights,
                'pickup_point' => $pickup,
                'description' => 'Hành trình được VietGo tuyển chọn với lịch trình cân bằng giữa khám phá, trải nghiệm địa phương và nghỉ ngơi.',
                'itinerary_summary' => 'Khởi hành – tham quan các điểm nổi bật – trải nghiệm ẩm thực địa phương – tự do khám phá – trở về.',
                'included_services' => 'Xe đưa đón, lưu trú, các bữa ăn theo chương trình, vé tham quan, bảo hiểm và hướng dẫn viên.',
                'is_active' => true,
                'min_price' => $price,
                'created_by' => $admin->getKey(),
            ]);
            TourDeparture::create([
                'tour_id' => $tour->getKey(),
                'departure_date' => today()->addDays(14 + $index * 5),
                'return_date' => today()->addDays(14 + $index * 5 + $days - 1),
                'max_seats' => 24,
                'available_seats' => 24,
                'adult_price' => $price,
                'child_price' => $price * .7,
                'guide_id' => $guide->getKey(),
                'status' => 'open',
            ]);
        }
    }
}
