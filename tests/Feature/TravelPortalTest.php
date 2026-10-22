<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourImage;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_a_customer_session(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_separates_upcoming_history_and_saved_tours_without_exposing_other_customers(): void
    {
        $this->withoutVite();
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $upcoming = Booking::factory()->for($customer)->withPassengers()->create();
        $ongoing = Booking::factory()->for($customer)->confirmed()->create([
            'departure_id' => TourDeparture::factory()->create([
                'departure_date' => today()->subDay(),
                'return_date' => today()->addDay(),
            ])->getKey(),
        ]);
        $completed = Booking::factory()->for($customer)->completed()->create();
        $cancelled = Booking::factory()->for($customer)->cancelled()->create();
        $past = Booking::factory()->for($customer)->confirmed()->create([
            'departure_id' => TourDeparture::factory()->past(),
        ]);
        $otherBooking = Booking::factory()->for($otherCustomer)->create();
        $savedTour = Tour::factory()->create(['title' => 'Hành trình riêng của tôi']);
        $otherSavedTour = Tour::factory()->create(['title' => 'Hành trình riêng của người khác']);
        $customer->favoriteTours()->attach($savedTour);
        $otherCustomer->favoriteTours()->attach($otherSavedTour);

        $this->withSession(['customer_id' => $customer->getKey()])
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee($customer->full_name)
            ->assertSee($upcoming->booking_code)
            ->assertSee($ongoing->booking_code)
            ->assertDontSee($completed->booking_code)
            ->assertDontSee($cancelled->booking_code)
            ->assertDontSee($past->booking_code)
            ->assertDontSee($otherBooking->booking_code)
            ->assertViewHas('summary', [
                'upcoming' => 2, 'completed' => 1, 'history' => 3, 'saved' => 1, 'total' => 5,
            ]);

        $this->get(route('customer.dashboard', ['tab' => 'history']))
            ->assertOk()
            ->assertSee($completed->booking_code)
            ->assertSee($cancelled->booking_code)
            ->assertSee($past->booking_code)
            ->assertDontSee($upcoming->booking_code)
            ->assertDontSee($ongoing->booking_code)
            ->assertDontSee($otherBooking->booking_code);

        $this->get(route('customer.dashboard', ['tab' => 'saved']))
            ->assertOk()->assertSee($savedTour->title)->assertDontSee($otherSavedTour->title);
    }

    public function test_booking_validation_restores_each_passenger_and_the_estimated_cost(): void
    {
        $this->withoutVite();
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create(['adult_price' => 1000000, 'child_price' => 500000]);
        $bookingUrl = route('bookings.create', $departure);

        $response = $this->withSession(['customer_id' => $customer->getKey()])
            ->from($bookingUrl)->post(route('bookings.store'), [
                'departure_id' => $departure->getKey(),
                'pickup_type' => 'self',
                'passengers' => [
                    ['full_name' => 'Nguyễn An <script>', 'passenger_type' => 'adult', 'id_card' => '012345678901'],
                    ['full_name' => 'Nguyễn Bình', 'passenger_type' => 'child', 'date_of_birth' => today()->addDay()->toDateString()],
                ],
            ]);

        $response->assertRedirect($bookingUrl)->assertSessionHasErrors('passengers.1.date_of_birth');
        $this->get($bookingUrl)->assertOk()
            ->assertSee('Nguyễn An &lt;script&gt;', false)
            ->assertSee('Nguyễn Bình')
            ->assertSee('name="passengers[1][full_name]"', false)
            ->assertSee('1.500.000₫');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_tour_details_render_real_photos_itinerary_and_available_departures(): void
    {
        $this->withoutVite();
        $tour = Tour::factory()->create();
        $image = TourImage::factory()->for($tour)->thumbnail()->create();
        $schedule = TourSchedule::factory()->for($tour)->create();
        $departure = TourDeparture::factory()->for($tour)->create();

        $this->get(route('tours.show', $tour))->assertOk()
            ->assertSee($tour->title)->assertSee($image->img_url)
            ->assertSee($schedule->activities)
            ->assertSee(route('bookings.create', $departure), false);
    }

    public function test_empty_dashboard_and_unknown_tab_render_safely(): void
    {
        $this->withoutVite();
        $customer = Customer::factory()->create();

        $this->withSession(['customer_id' => $customer->getKey()])
            ->get(route('customer.dashboard', ['tab' => 'unknown']))
            ->assertOk()->assertViewHas('tab', 'upcoming')
            ->assertSee('Bạn chưa có chuyến đi sắp tới.');
    }

    public function test_payment_form_displays_the_booking_amount_and_confirms_the_selected_method(): void
    {
        $this->withoutVite();
        $customer = Customer::factory()->create();
        $booking = Booking::factory()->for($customer)->withPassengers()->create(['total_amount' => 1500000]);

        $this->withSession(['customer_id' => $customer->getKey()])
            ->get(route('payments.create', $booking))->assertOk()
            ->assertSee('1.500.000₫')->assertSee('name="payment_method"', false);

        $this->post(route('payments.store', $booking), ['payment_method' => 'bank_transfer'])
            ->assertRedirect(route('customer.bookings.show', $booking));
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->getKey(),
            'payment_method' => 'bank_transfer',
            'amount' => 1500000,
        ]);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }
}
