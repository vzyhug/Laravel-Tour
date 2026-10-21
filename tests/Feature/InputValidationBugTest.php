<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Province;
use App\Models\Role;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Suite kiểm thử tự động ghi nhận các lỗ hổng / thiếu sót validation logic vùng nhập liệu.
 * Các test case này được Tester tạo ra để nhóm phát triển đối chiếu và đưa ra phương án sửa.
 */
class InputValidationBugTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): Customer
    {
        return Customer::create([
            'full_name' => 'Nguyễn Văn Test',
            'email' => 'customer@test.com',
            'phone' => '0901234567',
            'password_hash' => Hash::make('Password123'),
            'is_active' => true,
        ]);
    }

    private function createAdminEmployee(): Employee
    {
        $role = Role::firstOrCreate(['role_name' => 'Admin']);
        return Employee::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Tester',
            'email' => 'admintest@test.com',
            'phone' => '0912345678',
            'role_id' => $role->getKey(),
            'password_hash' => Hash::make('Admin@123'),
            'is_active' => true,
        ]);
    }

    private function createTour(): Tour
    {
        $category = TourCategory::firstOrCreate(['category_name' => 'Tour Biển']);
        $province = Province::firstOrCreate(['province_name' => 'Đà Nẵng', 'region' => 'Trung']);
        return Tour::create([
            'title' => 'Tour Khám Phá Đà Nẵng 3N2Đ',
            'category_id' => $category->getKey(),
            'province_id' => $province->getKey(),
            'duration_days' => 3,
            'duration_nights' => 2,
            'min_price' => 3000000,
            'is_active' => true,
        ]);
    }

    /**
     * BUG #1: Trang tìm kiếm /tours - 'price_min' > 'price_max' không hề bị chặn hoặc báo lỗi,
     * mà bị controller âm thầm hoán đổi vị trí (silent swap).
     */
    public function test_filter_tour_price_min_greater_than_max_returns_validation_error(): void
    {
        $response = $this->get('/tours?price_min=10.000.000&price_max=2.000.000');

        $response->assertRedirect(route('tours.index'));
        $response->assertSessionHasErrors(['price_max' => 'Giá đến phải lớn hơn hoặc bằng Giá từ.']);
    }

    /**
     * BUG #2: Trang cập nhật hồ sơ khách hàng (/account/profile) cho phép nhập số điện thoại tùy tiện (không check định dạng SĐT Việt Nam).
     */
    public function test_customer_profile_phone_requires_vietnam_phone_regex(): void
    {
        $customer = $this->createCustomer();

        $response = $this->withSession(['customer_id' => $customer->getKey()])
            ->put(route('customer.profile.update'), [
                'full_name' => 'Tên Hợp Lệ',
                'email' => $customer->email,
                'phone' => 'so-dien-thoai-sai-dinh-dang-1234',
            ]);

        $response->assertSessionHasErrors(['phone']);
        $this->assertDatabaseMissing('customers', [
            'customer_id' => $customer->getKey(),
            'phone' => 'so-dien-thoai-sai-dinh-dang-1234',
        ]);
    }

    /**
     * BUG #3: Trang đặt tour (/bookings) cho phép hành khách 'child' không nhập ngày sinh.
     */
    public function test_booking_child_passenger_requires_date_of_birth(): void
    {
        $customer = $this->createCustomer();
        $tour = $this->createTour();
        $departure = TourDeparture::create([
            'tour_id' => $tour->getKey(),
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(13),
            'max_seats' => 20,
            'available_seats' => 20,
            'adult_price' => 3000000,
            'child_price' => 1500000,
            'status' => 'open',
        ]);

        $response = $this->withSession(['customer_id' => $customer->getKey()])
            ->post(route('bookings.store'), [
                'departure_id' => $departure->getKey(),
                'passengers' => [
                    ['full_name' => 'Nguyễn Người Lớn', 'passenger_type' => 'adult', 'id_card' => '012345678901'],
                    ['full_name' => 'Bé Trẻ Em', 'passenger_type' => 'child', 'date_of_birth' => null],
                ],
            ]);

        $response->assertSessionHasErrors(['passengers.1.date_of_birth']);
    }

    /**
     * BUG #4: Admin thêm lịch khởi hành (/admin/departures) cho phép chọn ngày khởi hành trong quá khứ.
     */
    public function test_admin_departure_date_rejects_past_dates(): void
    {
        $admin = $this->createAdminEmployee();
        $tour = $this->createTour();

        $response = $this->withSession([
            'employee_id' => $admin->getKey(),
            'employee_name' => $admin->full_name,
            'employee_role' => 'Admin',
        ])->post(route('admin.departures.store'), [
            'tour_id' => $tour->getKey(),
            'departure_date' => '2020-01-01', // Ngày trong quá khứ
            'return_date' => '2020-01-03',
            'max_seats' => 20,
            'available_seats' => 20,
            'adult_price' => 3000000,
            'child_price' => 1500000,
            'status' => 'open',
        ]);

        $response->assertSessionHasErrors(['departure_date']);
        $this->assertDatabaseMissing('tour_departures', [
            'tour_id' => $tour->getKey(),
            'departure_date' => '2020-01-01 00:00:00',
        ]);
    }

    /**
     * BUG #5: Admin thêm lịch khởi hành cho phép giá trẻ em đắt hơn giá người lớn.
     */
    public function test_admin_departure_rejects_child_price_greater_than_adult_price(): void
    {
        $admin = $this->createAdminEmployee();
        $tour = $this->createTour();

        $response = $this->withSession([
            'employee_id' => $admin->getKey(),
            'employee_name' => $admin->full_name,
            'employee_role' => 'Admin',
        ])->post(route('admin.departures.store'), [
            'tour_id' => $tour->getKey(),
            'departure_date' => today()->addDays(5)->format('Y-m-d'),
            'return_date' => today()->addDays(7)->format('Y-m-d'),
            'max_seats' => 20,
            'available_seats' => 20,
            'adult_price' => 1000000, // Người lớn 1 triệu
            'child_price' => 9000000, // Trẻ em 9 triệu!
            'status' => 'open',
        ]);

        $response->assertSessionHasErrors(['child_price']);
        $this->assertDatabaseMissing('tour_departures', [
            'tour_id' => $tour->getKey(),
            'adult_price' => 1000000,
            'child_price' => 9000000,
        ]);
    }

    /**
     * BUG #6: Admin thêm Tour (/admin/tours) cho phép số đêm lớn hơn số ngày vô lý (ví dụ 1 ngày 10 đêm).
     */
    public function test_admin_tour_rejects_duration_nights_greater_than_days(): void
    {
        $admin = $this->createAdminEmployee();
        $category = TourCategory::firstOrCreate(['category_name' => 'Tour Núi']);
        $province = Province::firstOrCreate(['province_name' => 'Lào Cai', 'region' => 'B?c']);

        $response = $this->withSession([
            'employee_id' => $admin->getKey(),
            'employee_name' => $admin->full_name,
            'employee_role' => 'Admin',
        ])->post(route('admin.tours.store'), [
            'title' => 'Tour Vô Lý 1 Ngày 10 Đêm',
            'category_id' => $category->getKey(),
            'province_id' => $province->getKey(),
            'duration_days' => 1,
            'duration_nights' => 10, // 10 đêm cho tour 1 ngày!
            'min_price' => 2000000,
        ]);

        $response->assertSessionHasErrors(['duration_nights']);
        $this->assertDatabaseMissing('tours', [
            'title' => 'Tour Vô Lý 1 Ngày 10 Đêm',
        ]);
    }

    /**
     * BUG #7: Đánh giá tour (/account/bookings/{booking}/review) cho phép khách hàng review
     * ngay khi chuyến đi còn chưa khởi hành (status=confirmed nhưng departure_date ở tương lai).
     */
    public function test_customer_cannot_review_tour_before_departure_date(): void
    {
        $customer = $this->createCustomer();
        $tour = $this->createTour();
        $departure = TourDeparture::create([
            'tour_id' => $tour->getKey(),
            'departure_date' => today()->addDays(30), // 1 tháng nữa mới đi
            'return_date' => today()->addDays(33),
            'max_seats' => 20,
            'available_seats' => 18,
            'adult_price' => 3000000,
            'child_price' => 1500000,
            'status' => 'open',
        ]);

        $booking = $customer->bookings()->create([
            'booking_code' => 'BKTEST001',
            'departure_id' => $departure->getKey(),
            'total_adults' => 2,
            'total_children' => 0,
            'total_amount' => 6000000,
            'status' => 'confirmed',
        ]);

        $response = $this->withSession(['customer_id' => $customer->getKey()])
            ->post(route('customer.bookings.review', $booking), [
                'rating' => 5,
                'feedback' => 'Tour tuyệt vời dù tôi chưa đi!',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('reviews', [
            'customer_id' => $customer->getKey(),
            'tour_id' => $tour->getKey(),
        ]);
    }
}
