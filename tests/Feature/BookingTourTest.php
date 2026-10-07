<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_available(): void
    {
        $this->get('/')->assertOk()->assertSee('VietGo');
    }

    public function test_customer_can_book_available_departure(): void
    {
        $customer = Customer::create(['full_name' => 'Test Customer', 'email' => 'customer@example.com', 'phone' => '0901234567', 'password_hash' => Hash::make('Password1')]);
        $category = TourCategory::create(['category_name' => 'Tour Test']);
        $province = Province::create(['province_name' => 'Đà Nẵng', 'region' => 'Trung']);
        $tour = Tour::create(['title' => 'Tour kiểm thử', 'category_id' => $category->getKey(), 'province_id' => $province->getKey(), 'duration_days' => 2, 'duration_nights' => 1, 'min_price' => 1000000]);
        $departure = TourDeparture::create(['tour_id' => $tour->getKey(), 'departure_date' => today()->addWeek(), 'return_date' => today()->addWeek()->addDay(), 'max_seats' => 10, 'available_seats' => 10, 'adult_price' => 1000000, 'child_price' => 500000, 'status' => 'open']);

        $response = $this->withSession(['customer_id' => $customer->getKey()])->post(route('bookings.store'), [
            'departure_id' => $departure->getKey(),
            'passengers' => [
                ['full_name' => 'Nguyễn Văn A', 'passenger_type' => 'adult', 'id_card' => '012345678901'],
                ['full_name' => 'Nguyễn Văn B', 'passenger_type' => 'child', 'date_of_birth' => '2018-01-01'],
            ],
        ]);

        $booking = $customer->bookings()->firstOrFail();
        $response->assertRedirect(route('payments.create', $booking));
        $this->assertSame(8, $departure->fresh()->available_seats);
        $this->assertSame('1500000.00', $booking->total_amount);
    }
}
