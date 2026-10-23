<?php

namespace Tests\Feature;

use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\TourDeparture;
use App\Services\TourQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OtaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function checkout(Customer $customer, TourDeparture $departure): array
    {
        return [
            'departure_id' => $departure->getKey(),
            'checkout_key' => (string) Str::uuid(),
            'quote_token' => app(TourQuote::class)->issue($departure->fresh(), $customer->getKey()),
            'expected_total' => (int) $departure->adult_price,
            'contact_name' => 'Người đặt thay', 'contact_email' => 'contact@example.test', 'contact_phone' => '0901234567',
            'policy_accepted' => '1', 'pickup_type' => 'self',
            'passengers' => [['full_name' => 'Người tham gia', 'passenger_type' => 'adult', 'id_card' => '012345678901']],
        ];
    }

    public function test_retry_creates_one_booking_and_changed_payload_cannot_reuse_the_key(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create(['max_seats' => 1, 'available_seats' => 1]);
        $payload = $this->checkout($customer, $departure);
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->get(route('bookings.create', $departure))->assertOk()->assertSee('name="quote_token"', false)->assertSee('Thông tin người liên hệ');
        $this->post(route('bookings.store'), $payload)->assertSessionHasNoErrors();
        $booking = Booking::sole();
        $this->post(route('bookings.store'), $payload)->assertRedirect(route('payments.create', $booking));
        $this->assertSame(1, Booking::count());
        $this->assertSame(0, $departure->fresh()->available_seats);
        $this->assertSame(1, $booking->events()->where('type', 'held')->count());
        $this->assertSame('contact@example.test', $booking->service_snapshot['contact']['email']);
        $payload['contact_name'] = 'Changed';
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('checkout_key');
        $this->assertSame(1, Booking::count());
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()])->post(route('bookings.store'), $this->checkout($other, $departure->fresh()))
            ->assertSessionHasErrors('passengers');
        $this->assertSame(0, $departure->fresh()->available_seats);
        $this->assertSame(1, Booking::count());
    }

    public function test_expired_tampered_or_foreign_quotes_and_changed_prices_do_not_reserve_seats(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create();
        $seats = $departure->available_seats;
        $this->withSession(['customer_id' => $customer->getKey()]);
        $payload = $this->checkout($customer, $departure);
        $this->travel(config('booking.quote_minutes') + 1)->minutes();
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('quote_token');
        $this->travelBack();
        $payload = $this->checkout($customer, $departure);
        $departure->update(['adult_price' => $departure->adult_price + 500000]);
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('quote_token');
        $payload = $this->checkout($customer, $departure->fresh());
        $payload['quote_token'] = 'tampered';
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('quote_token');
        $other = Customer::factory()->create();
        $payload = $this->checkout($other, $departure->fresh());
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('quote_token');
        $this->assertSame(0, Booking::count());
        $this->assertSame($seats, $departure->fresh()->available_seats);
    }

    public function test_consent_and_final_total_are_checked_before_holding_inventory(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $payload = $this->checkout($customer, $departure);
        $payload['policy_accepted'] = '0';
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('policy_accepted');
        $payload['policy_accepted'] = '1';
        $payload['expected_total'] = 1;
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('quote_token');
        $this->assertSame(0, Booking::count());
    }

    public function test_missing_quote_cannot_bypass_price_confirmation(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create();
        $payload = $this->checkout($customer, $departure);
        unset($payload['quote_token'], $payload['checkout_key']);
        $this->withSession(['customer_id' => $customer->getKey()])->post(route('bookings.store'), $payload)
            ->assertSessionHasErrors(['quote_token', 'checkout_key']);
        $this->assertSame(0, Booking::count());
    }

    public function test_expiry_releases_inventory_once_and_late_payment_cannot_confirm_booking(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create(['max_seats' => 1, 'available_seats' => 1]);
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->post(route('bookings.store'), $this->checkout($customer, $departure))->assertSessionHasNoErrors();
        $booking = Booking::sole();
        $deadline = $booking->payment_expires_at->toIso8601String();
        $this->get(route('payments.create', $booking))->assertOk();
        $this->assertSame($deadline, $booking->fresh()->payment_expires_at->toIso8601String());
        $this->travel(config('booking.payment_minutes') + 1)->minutes();
        $this->post(route('payments.store', $booking), ['payment_method' => 'card'])->assertStatus(409);
        $this->artisan('bookings:expire')->assertSuccessful();
        $this->assertFalse(app(ExpireBooking::class)->handle($booking));
        $this->assertSame(1, $departure->fresh()->available_seats);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->expired_at);
        $this->assertSame(1, $booking->events()->where('type', 'hold_expired')->count());
        $this->assertNull($booking->payment);
        $this->get(route('customer.bookings.show', $booking))->assertOk()->assertSee('Hết hạn giữ chỗ');
        $this->get(route('customer.bookings.voucher', $booking))->assertNotFound();
    }

    public function test_failed_mock_payment_can_retry_and_paid_inventory_is_not_expired(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create(['max_seats' => 1, 'available_seats' => 1]);
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->post(route('bookings.store'), $this->checkout($customer, $departure))->assertSessionHasNoErrors();
        $booking = Booking::sole();
        $this->post(route('payments.store', $booking), ['payment_method' => 'momo', 'demo_result' => 'failed'])->assertSessionHasErrors('payment');
        $this->assertNull($booking->payment);
        $this->post(route('payments.store', $booking), ['payment_method' => 'momo', 'demo_result' => 'success'])->assertRedirect(route('customer.bookings.show', $booking));
        $this->travel(config('booking.payment_minutes') + 1)->minutes();
        $this->artisan('bookings:expire')->assertSuccessful();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(0, $departure->fresh()->available_seats);
        $this->assertSame(1, $booking->payment()->count());
        $this->assertSame(1, $booking->events()->where('type', 'payment_failed_demo')->count());
        $this->assertSame(1, $booking->events()->where('type', 'payment_captured_demo')->count());
    }

    public function test_snapshot_survives_catalog_changes_and_voucher_requires_paid_ownership(): void
    {
        $customer = Customer::factory()->create();
        $departure = TourDeparture::factory()->create(['adult_price' => 1500000]);
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->post(route('bookings.store'), $this->checkout($customer, $departure))->assertSessionHasNoErrors();
        $booking = Booking::sole();
        $title = $booking->service_snapshot['title'];
        $this->get(route('customer.bookings.voucher', $booking))->assertNotFound();
        $this->post(route('payments.store', $booking), ['payment_method' => 'cash']);
        $departure->tour->update(['title' => 'Changed title']);
        $departure->update(['adult_price' => 99000000]);
        config(['booking.cancel_before_days' => 200, 'booking.partial_refund_percent' => 1]);
        $this->assertSame(5, $booking->fresh()->policyValue('cancel_before_days'));
        $this->get(route('customer.bookings.voucher', $booking))->assertOk()->assertSee($title)->assertDontSee('012345678901')->assertDontSee('Changed title');
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee('1.500.000₫')->assertDontSee('99.000.000₫');
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()])->get(route('customer.bookings.voucher', $booking))->assertForbidden();
        $this->withSession(['customer_id' => $customer->getKey()]);
        $this->post(route('customer.bookings.cancel', $booking), ['cancel_reason' => 'Demo cancellation']);
        $this->get(route('customer.bookings.voucher', $booking))->assertNotFound();
    }

    public function test_date_and_group_search_only_returns_departures_with_enough_capacity(): void
    {
        $date = today()->addDays(12)->format('Y-m-d');
        $available = TourDeparture::factory()->create(['departure_date' => $date, 'status' => 'open', 'available_seats' => 3]);
        TourDeparture::factory()->create(['departure_date' => $date, 'status' => 'open', 'available_seats' => 1]);
        TourDeparture::factory()->create(['departure_date' => $date, 'status' => 'cancelled', 'available_seats' => 10]);
        $this->get(route('tours.index', ['departure_date' => $date, 'guests' => 3]))->assertOk()
            ->assertViewHas('tours', fn ($tours) => $tours->pluck('tour_id')->all() === [$available->tour_id]);
        $this->get(route('tours.show', ['tour' => $available->tour_id, 'departure_date' => $date, 'guests' => 3]))
            ->assertOk()->assertSee('guests=3');
        $customer = Customer::factory()->create();
        $this->withSession(['customer_id' => $customer->getKey()])->get(route('bookings.create', ['departure' => $available, 'guests' => 3]))
            ->assertOk()->assertSee('name="passengers[2][full_name]"', false);
        $this->get(route('tours.index', ['guests' => 21]))->assertSessionHasErrors('guests');
        $this->get(route('home'))->assertOk()->assertSee('Tìm & đặt dịch vụ demo', false)->assertSee('Bản demo đồ án');
        $this->get(route('support'))->assertOk();
    }
}
