<?php

namespace Tests\Feature;

use App\Actions\AcceptItineraryQuote;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerItinerary;
use App\Models\Employee;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomTourCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.checkout_methods' => ['sepay', 'vietqr_demo', 'bank_transfer', 'momo', 'cash', 'card']]);
    }

    private function quotedPlan(array $attributes = []): array
    {
        $this->withoutVite();
        $customer = Customer::factory()->create();
        $tour = Tour::factory()->create(['title' => 'Tour tham chiếu', 'duration_days' => 2, 'duration_nights' => 1]);
        $plan = CustomerItinerary::create(['customer_id' => $customer->getKey(), 'tour_id' => $tour->getKey(), 'version' => 1]);
        $quote = $plan->consultations()->create([
            'request_key' => (string) Str::uuid(), 'itinerary_version' => 1,
            'snapshot' => ['tour' => 'Tên tour khi gửi', 'days' => [['number' => 1, 'title' => 'Ngày đầu'], ['number' => 2, 'title' => 'Ngày cuối']],
                'places' => [], 'items' => [['day_number' => 1, 'place_id' => null, 'title' => 'Nội dung đã gửi', 'start_time' => '09:00', 'duration_minutes' => 60, 'notes' => 'Điều kiện lịch trình gốc']]],
            'travel_date' => today()->addMonth(), 'adults' => 2, 'children' => 0,
            'contact_name' => $customer->full_name, 'contact_phone' => $customer->phone,
            'contact_email' => $customer->email, 'status' => 'quoted', 'version' => 1,
            'quoted_total' => 5500001, 'reply' => 'Giá trọn nhóm gồm xe và hướng dẫn.',
            'valid_until' => today()->addWeek(), ...$attributes,
        ]);

        return [$customer, $tour, $plan, $quote];
    }

    private function acceptance(): array
    {
        return ['version' => 1, 'terms_accepted' => true, 'pickup_type' => 'self',
            'passengers' => [['full_name' => 'Nguyễn An', 'id_card' => '012345678901'], ['full_name' => 'Trần Bình', 'id_card' => '098765432109']]];
    }

    public function test_acceptance_creates_one_private_booking_with_frozen_group_price_and_itinerary(): void
    {
        [$customer, $tour, $plan, $quote] = $this->quotedPlan();
        $plan->items()->create(['day_number' => 1, 'position' => 1, 'title' => 'Sửa sau khi gửi']);
        $plan->update(['version' => 2]);
        $this->withSession(['customer_id' => $customer->getKey()])
            ->post(route('itinerary-consultations.accept', $quote), [...$this->acceptance(), 'total_amount' => 1, 'quoted_total' => 1])
            ->assertRedirect(route('payments.create', Booking::firstOrFail()));

        $booking = Booking::with('departure', 'passengers')->firstOrFail();
        $this->assertSame('5500001.00', $booking->total_amount);
        $this->assertTrue($booking->departure->is_private);
        $this->assertSame(0, $booking->departure->available_seats);
        $this->assertSame(2, $booking->departure->max_seats);
        $this->assertCount(2, $booking->passengers);
        $this->assertSame('Tên tour khi gửi', $booking->quote_snapshot['tour']);
        $this->assertSame('Nội dung đã gửi', $booking->quote_snapshot['items'][0]['title']);
        $this->assertSame(1, $booking->quote_snapshot['itinerary_version']);
        $this->assertSame($quote->travel_date->copy()->addDay()->toDateString(), $booking->departure->return_date->toDateString());
        $this->assertNotNull($booking->hold_expires_at);
        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->accepted_at);

        // A retry with the original version returns the original booking unchanged.
        $this->post(route('itinerary-consultations.accept', $quote), $this->acceptance())->assertRedirect(route('payments.create', $booking));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('tour_departures', 1);
        $this->get(route('itinerary-consultations.show', $quote))->assertOk()->assertSee($booking->booking_code);
        $this->get(route('payments.create', $booking))->assertOk()->assertSee('Nội dung đã gửi')->assertSee('5.500.001');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_quote_checkout_cannot_be_used_by_another_customer(): void
    {
        [, , , $quote] = $this->quotedPlan();
        $this->withSession(['customer_id' => Customer::factory()->create()->getKey()]);
        $this->postJson(route('itinerary-consultations.accept', $quote), $this->acceptance())->assertForbidden();
        $this->postJson(route('itinerary-consultations.reject', $quote), ['version' => 1])->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame('quoted', $quote->fresh()->status);
    }

    public function test_stale_expired_and_invalid_group_quotes_cannot_create_orders(): void
    {
        [$customer, , , $quote] = $this->quotedPlan();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $quote->update(['version' => 2]);
        $this->postJson(route('itinerary-consultations.accept', $quote), $this->acceptance())->assertConflict();
        $quote->update(['version' => 1, 'valid_until' => today()->subDay()]);
        $this->postJson(route('itinerary-consultations.accept', $quote), $this->acceptance())->assertUnprocessable();
        $quote->update(['valid_until' => today()->addWeek()]);
        $data = $this->acceptance();
        array_pop($data['passengers']);
        $this->postJson(route('itinerary-consultations.accept', $quote), $data)->assertUnprocessable()->assertJsonValidationErrors('passengers');
        $this->postJson(route('itinerary-consultations.accept', $quote), [...$this->acceptance(), 'terms_accepted' => false])->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('tour_departures', 0);
    }

    public function test_rejection_is_terminal_without_creating_a_booking(): void
    {
        [$customer, , , $quote] = $this->quotedPlan();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->post(route('itinerary-consultations.reject', $quote), ['version' => 1])->assertRedirect();
        $this->post(route('itinerary-consultations.reject', $quote), ['version' => 1])->assertRedirect();
        $this->assertSame('rejected', $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->rejected_at);
        $this->postJson(route('itinerary-consultations.accept', $quote), [...$this->acceptance(), 'version' => 2])->assertUnprocessable();
        $this->get(route('itinerary-consultations.show', $quote))->assertOk()->assertSee('Đã từ chối báo giá');
        $this->get(route('tour-itinerary.index'))->assertOk()->assertSee('Đã từ chối báo giá');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_staff_cannot_reprice_a_quote_after_acceptance(): void
    {
        [$customer, , , $quote] = $this->quotedPlan();
        $booking = app(AcceptItineraryQuote::class)->handle($customer->getKey(), $quote, $this->acceptance());
        $staff = Employee::factory()->admin()->create();
        $this->withSession(['employee_id' => $staff->getKey(), 'employee_role' => 'Admin']);
        $this->patchJson(route('admin.itinerary-consultations.update', $quote), [
            'version' => 2, 'status' => 'quoted', 'quoted_total' => 1,
            'valid_until' => today()->addDay()->toDateString(), 'reply' => 'Thay đổi giá sau chốt',
        ])->assertUnprocessable();
        $this->assertSame(5500001, $quote->fresh()->quoted_total);
        $this->assertSame('5500001.00', $booking->fresh()->total_amount);
        $this->get(route('admin.itinerary-consultations.index', ['status' => 'accepted']))->assertOk();
        $this->get(route('admin.itinerary-consultations.show', $quote))->assertOk()->assertDontSee('Lưu &amp; gửi phản hồi cho khách', false);
    }

    public function test_private_departures_stay_hidden_and_cannot_use_public_booking_or_coupons(): void
    {
        [$customer, $tour, , $quote] = $this->quotedPlan();
        $booking = app(AcceptItineraryQuote::class)->handle($customer->getKey(), $quote, $this->acceptance());
        // Expired/cancelled bookings may return seats; privacy must still be enforced.
        $booking->departure->update(['status' => 'open', 'available_seats' => 2]);
        $this->get(route('tours.show', $tour))->assertOk()->assertViewHas('tour', fn ($shown) => $shown->departures->isEmpty());
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()]);
        $this->get(route('bookings.create', $booking->departure))->assertNotFound();
        $this->postJson(route('bookings.store'), [...$this->acceptance(), 'departure_id' => $booking->departure_id])->assertUnprocessable();
        $this->postJson(route('coupons.check'), ['departure_id' => $booking->departure_id, 'adults' => 2, 'code' => 'TEST'])->assertNotFound();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame(2, $booking->departure->fresh()->available_seats);
    }

    public function test_payment_and_invoice_use_accepted_total_even_after_source_plan_and_tour_change(): void
    {
        [$customer, $tour, $plan, $quote] = $this->quotedPlan();
        $booking = app(AcceptItineraryQuote::class)->handle($customer->getKey(), $quote, $this->acceptance());
        $tour->update(['title' => 'Tên tour mới', 'duration_days' => 5]);
        $plan->items()->create(['day_number' => 1, 'position' => 1, 'title' => 'Hoạt động mới']);
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->post(route('payments.store', $booking), ['payment_method' => 'cash', 'demo_outcome' => 'succeeded'])->assertRedirect();
        $booking->refresh();
        $this->assertSame('5500001.00', $booking->payment->amount);
        $this->assertSame('5500001.00', $booking->bill->final_amount);
        $this->assertSame('Tên tour khi gửi', $booking->quote_snapshot['tour']);
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee('5.500.001');
    }

    public function test_acceptance_preserves_explicit_duration_and_null_pickup_from_the_sent_snapshot(): void
    {
        [$customer, $tour, , $quote] = $this->quotedPlan();
        $quote->update(['snapshot' => [
            ...$quote->snapshot, 'duration_days' => 4, 'duration_nights' => 3,
            'days' => [], 'pickup_point' => null,
        ]]);
        $tour->update(['duration_days' => 7, 'duration_nights' => 6, 'pickup_point' => 'Điểm đón mới sau báo giá']);

        $booking = app(AcceptItineraryQuote::class)->handle($customer->getKey(), $quote, $this->acceptance());

        $this->assertSame($quote->travel_date->copy()->addDays(3)->toDateString(), $booking->departure->return_date->toDateString());
        $this->assertSame(4, $booking->quote_snapshot['duration_days']);
        $this->assertSame(3, $booking->quote_snapshot['duration_nights']);
        $this->assertArrayHasKey('pickup_point', $booking->quote_snapshot);
        $this->assertNull($booking->quote_snapshot['pickup_point']);
        $this->assertSame('5500001.00', $booking->total_amount);
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->get(route('customer.bookings.show', $booking))->assertOk()->assertDontSee('Điểm đón mới sau báo giá')->assertSee('điểm tập trung sẽ thông báo');
        $this->post(route('payments.store', $booking), ['payment_method' => 'cash', 'demo_outcome' => 'succeeded'])->assertRedirect();
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertDontSee('Điểm đón mới sau báo giá')->assertSee('sẽ thông báo');
    }

    public function test_quote_is_valid_until_end_of_day_and_its_hold_does_not_outlive_the_quote(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(23, 50));
        [$customer, , , $quote] = $this->quotedPlan(['valid_until' => today()]);

        $booking = app(AcceptItineraryQuote::class)->handle($customer->getKey(), $quote, $this->acceptance());

        $this->assertSame(today()->toDateString().' 23:59:59', $booking->hold_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('accepted', $quote->fresh()->status);
    }

    public function test_same_day_quote_hold_is_capped_at_departure_and_cannot_be_accepted_at_departure(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(8, 50));
        [$customer, , , $quote] = $this->quotedPlan(['travel_date' => today(), 'valid_until' => today()]);
        $quote->update(['snapshot' => [...$quote->snapshot, 'departure_time' => '09:00']]);

        $booking = app(AcceptItineraryQuote::class)->handle($customer->getKey(), $quote, $this->acceptance());
        $this->assertSame(today()->toDateString().' 09:00:00', $booking->hold_expires_at->format('Y-m-d H:i:s'));

        [$other, , , $lateQuote] = $this->quotedPlan(['travel_date' => today(), 'valid_until' => today()]);
        $lateQuote->update(['snapshot' => [...$lateQuote->snapshot, 'departure_time' => '09:00']]);
        $this->travelTo(now()->setTime(9, 0));
        $this->withSession(['customer_id' => $other->getKey()])
            ->postJson(route('itinerary-consultations.accept', $lateQuote), $this->acceptance())->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('tour_departures', 1);
        $this->assertSame('quoted', $lateQuote->fresh()->status);
    }
}
