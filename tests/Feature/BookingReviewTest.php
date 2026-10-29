<?php

namespace Tests\Feature;

use App\Models\{Booking, BookingPassenger, Customer, Employee, PassengerAttendance, Payment, Review, TourDeparture};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_ended_filter_includes_completed_and_unclosed_past_departures(): void
    {
        $this->withoutVite();
        $guide = Employee::factory()->guide()->create();
        $past = TourDeparture::factory()->create(['guide_id' => $guide->getKey(), 'departure_date' => today()->subDays(4), 'return_date' => today()->subDay()]);
        $completed = TourDeparture::factory()->create(['guide_id' => $guide->getKey(), 'operation_status' => 'completed']);
        TourDeparture::factory()->create(['guide_id' => $guide->getKey()]);
        $this->withSession(['employee_id' => $guide->getKey(), 'employee_role' => 'Guide'])
            ->get(route('operations.index', ['trip_status' => 'ended']))->assertOk()
            ->assertViewHas('departures', fn ($rows) => $rows->total() === 2
                && $rows->contains('departure_id', $past->getKey())
                && $rows->contains('departure_id', $completed->getKey()));
    }

    public function test_guide_passenger_count_excludes_pending_and_cancelled_bookings(): void
    {
        $this->withoutVite();
        $guide = Employee::factory()->guide()->create();
        $departure = TourDeparture::factory()->create(['guide_id' => $guide->getKey()]);
        foreach (['confirmed', 'completed', 'pending', 'cancelled'] as $status) {
            $booking = Booking::factory()->create(['departure_id' => $departure->getKey(), 'status' => $status]);
            BookingPassenger::factory()->create(['booking_id' => $booking->getKey()]);
        }
        $this->withSession(['employee_id' => $guide->getKey(), 'employee_role' => 'Guide'])
            ->get(route('operations.index'))->assertOk()
            ->assertViewHas('departures', fn ($departures) => $departures->first()->confirmed_passengers_count === 2);
    }

    private function eligibleBooking(): Booking
    {
        $departure = TourDeparture::factory()->create(['operation_status' => 'completed', 'status' => 'full']);
        $booking = Booking::factory()->completed()->create(['departure_id' => $departure->getKey()]);
        Payment::factory()->create(['booking_id' => $booking->getKey()]);
        $passenger = BookingPassenger::factory()->create(['booking_id' => $booking->getKey()]);
        PassengerAttendance::create(['passenger_id' => $passenger->getKey(), 'status' => 'present', 'checked_at' => now()]);
        return $booking;
    }

    public function test_only_paid_attended_completed_bookings_can_review(): void
    {
        $booking = $this->eligibleBooking();
        $this->assertTrue($booking->canReview());
        $booking->payment()->update(['status' => 'failed']);
        $this->assertFalse($booking->canReview());
        $booking->payment()->update(['status' => 'completed']);
        PassengerAttendance::query()->update(['status' => 'absent']);
        $this->assertFalse($booking->canReview());
        PassengerAttendance::query()->update(['status' => 'present']);
        $booking->update(['status' => 'confirmed']);
        $this->assertFalse($booking->canReview());
    }

    public function test_owner_can_submit_and_update_one_review_per_booking(): void
    {
        $booking = $this->eligibleBooking();
        $this->withSession(['customer_id' => $booking->customer_id]);
        foreach ([4, 5] as $rating) {
            $this->post(route('customer.bookings.review', $booking), ['rating' => $rating, 'feedback' => 'Enjoyed this trip'])->assertRedirect();
        }
        $this->assertDatabaseCount('review', 1);
        $this->assertDatabaseHas('review', ['booking_id' => $booking->getKey(), 'rating' => 5]);
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()])->post(route('customer.bookings.review', $booking), ['rating' => 1, 'feedback' => 'Wrong owner'])->assertForbidden();
    }

    public function test_guide_only_sees_reviews_for_assigned_departure(): void
    {
        $this->withoutVite();
        $booking = $this->eligibleBooking();
        $guide = Employee::factory()->guide()->create();
        $booking->departure->update(['guide_id' => $guide->getKey()]);
        Review::create(['booking_id' => $booking->getKey(), 'customer_id' => $booking->customer_id, 'tour_id' => $booking->departure->tour_id, 'rating' => 5, 'feedback' => 'Feedback for assigned departure']);
        $other = $this->eligibleBooking();
        Review::create(['booking_id' => $other->getKey(), 'customer_id' => $other->customer_id, 'tour_id' => $other->departure->tour_id, 'rating' => 1, 'feedback' => 'Private other departure feedback']);
        $this->withSession(['employee_id' => $guide->getKey(), 'employee_role' => 'Guide'])
            ->get(route('operations.show', $booking->departure))->assertOk()
            ->assertSee('Feedback for assigned departure')->assertDontSee('Private other departure feedback');
        $this->get(route('operations.show', $other->departure))->assertForbidden();
    }
}
