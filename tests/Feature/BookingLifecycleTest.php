<?php

namespace Tests\Feature;

use App\Actions\ChangeBookingDeparture;
use App\Actions\CreateBooking;
use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\TourDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_expiry_returns_seats_only_once_and_preserves_cancelled_departure(): void
    {
        $departure = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 3, 'status' => 'cancelled']);
        $booking = Booking::factory()->create(['departure_id' => $departure->getKey(), 'total_adults' => 2, 'hold_expires_at' => now()->subMinute()]);
        $this->assertTrue(app(ExpireBooking::class)->handle($booking));
        $this->assertFalse(app(ExpireBooking::class)->handle($booking));
        $this->assertSame(5, $departure->fresh()->available_seats);
        $this->assertSame('cancelled', $departure->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_scheduler_ignores_paid_unexpired_and_legacy_bookings(): void
    {
        $paid = Booking::factory()->create(['hold_expires_at' => now()->subMinute()]);
        Payment::factory()->create(['booking_id' => $paid->getKey()]);
        $unexpired = Booking::factory()->create(['hold_expires_at' => now()->addMinute()]);
        $legacy = Booking::factory()->create(['hold_expires_at' => null]);
        $this->artisan('bookings:expire')->assertSuccessful();
        foreach ([$paid, $unexpired, $legacy] as $booking) {
            $this->assertSame('pending', $booking->fresh()->status);
        }
    }

    public function test_new_booking_has_a_hold_deadline(): void
    {
        $departure = TourDeparture::factory()->create();
        $customer = Customer::factory()->create();
        $booking = app(CreateBooking::class)->handle($customer->getKey(), [
            'departure_id' => $departure->getKey(), 'pickup_type' => 'self',
            'passengers' => [['full_name' => 'Khách kiểm thử', 'id_card' => '012345678901']],
        ]);
        $this->assertEqualsWithDelta(now()->addMinutes(config('booking.hold_minutes'))->timestamp, $booking->hold_expires_at->timestamp, 2);
        $this->assertSame($departure->available_seats - 1, $departure->fresh()->available_seats);
    }

    public function test_customer_changes_tour_with_repricing_history_and_unchanged_deadline(): void
    {
        $source = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 3]);
        $target = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 2, 'adult_price' => 1500000]);
        $booking = Booking::factory()->create([
            'departure_id' => $source->getKey(), 'total_adults' => 2, 'total_amount' => 2000000,
            'hold_expires_at' => now()->addMinutes(20), 'pickup_type' => 'door', 'pickup_address' => 'Địa chỉ cũ',
        ]);
        $deadline = $booking->hold_expires_at->toDateTimeString();
        $this->withSession(['customer_id' => $booking->customer_id])
            ->get(route('customer.booking-changes.edit', $booking))->assertOk()->assertSee('Đổi lịch');
        $payload = ['departure_id' => $target->getKey(), 'expected_departure_id' => $source->getKey(), 'reason' => 'Thay đổi kế hoạch', 'accept_price' => 1, 'quoted_prices' => [$target->getKey() => ['adult_price' => $target->adult_price, 'child_price' => $target->child_price]]];
        $this->post(route('customer.booking-changes.update', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertSame($target->getKey(), $booking->fresh()->departure_id);
        $this->assertSame('3000000.00', $booking->fresh()->total_amount);
        $this->assertSame($deadline, $booking->fresh()->hold_expires_at->toDateTimeString());
        $this->assertSame('self', $booking->fresh()->pickup_type);
        $this->assertSame(5, $source->fresh()->available_seats);
        $this->assertSame(0, $target->fresh()->available_seats);
        $this->assertSame('full', $target->fresh()->status);
        $this->assertDatabaseHas('booking_changes', ['booking_id' => $booking->getKey(), 'from_departure_id' => $source->getKey(), 'to_departure_id' => $target->getKey()]);
        $this->post(route('customer.booking-changes.update', $booking), $payload)->assertSessionHasErrors('departure_id');
        $this->assertSame(1, $booking->changes()->count());
        $this->assertSame(0, $target->fresh()->available_seats);
    }

    public function test_failed_change_preserves_inventory_and_booking(): void
    {
        $source = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 3]);
        $target = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 1]);
        $booking = Booking::factory()->create(['departure_id' => $source->getKey(), 'total_adults' => 2]);
        try {
            app(ChangeBookingDeparture::class)->handle($booking, $booking->customer_id, $target->getKey(), $source->getKey(), 'Đổi lịch');
            $this->fail('A full departure must reject the change.');
        } catch (ValidationException) {
            $this->assertSame($source->getKey(), $booking->fresh()->departure_id);
            $this->assertSame(3, $source->fresh()->available_seats);
            $this->assertSame(1, $target->fresh()->available_seats);
            $this->assertSame(0, $booking->changes()->count());
        }
    }

    public function test_paid_booking_and_other_customer_cannot_change_departure(): void
    {
        $booking = Booking::factory()->create();
        Payment::factory()->create(['booking_id' => $booking->getKey(), 'status' => 'pending']);
        $target = TourDeparture::factory()->create();
        $payload = ['departure_id' => $target->getKey(), 'expected_departure_id' => $booking->departure_id, 'reason' => 'Đổi lịch', 'accept_price' => 1, 'quoted_prices' => [$target->getKey() => ['adult_price' => $target->adult_price, 'child_price' => $target->child_price]]];
        $this->withSession(['customer_id' => $booking->customer_id])->post(route('customer.booking-changes.update', $booking), $payload)->assertSessionHasErrors('departure_id');
        $this->withSession(['customer_id' => Customer::factory()->create()->getKey()])->post(route('customer.booking-changes.update', $booking), $payload)->assertForbidden();
        $this->assertSame(0, $booking->changes()->count());
    }

    public function test_change_recalculates_existing_coupon_discount(): void
    {
        $coupon = Coupon::create(['code' => 'CHANGE10', 'discount_percent' => 10, 'min_order' => 100000, 'is_active' => true]);
        $booking = Booking::factory()->create(['coupon_id' => $coupon->getKey(), 'total_adults' => 1]);
        $target = TourDeparture::factory()->create(['adult_price' => 2000000]);
        app(ChangeBookingDeparture::class)->handle($booking, $booking->customer_id, $target->getKey(), $booking->departure_id, 'Đổi lịch');
        $this->assertSame('1800000.00', $booking->fresh()->total_amount);
        $this->assertSame('200000.00', $booking->fresh()->discount_amount);
    }

    public function test_price_changed_after_opening_form_requires_fresh_confirmation_without_moving_seats(): void
    {
        $source = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 3]);
        $target = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 5, 'adult_price' => 1500000]);
        $booking = Booking::factory()->create(['departure_id' => $source->getKey(), 'total_adults' => 2, 'total_amount' => 2000000]);
        $payload = [
            'departure_id' => $target->getKey(), 'expected_departure_id' => $source->getKey(),
            'reason' => 'Đổi lịch', 'accept_price' => 1,
            'quoted_prices' => [$target->getKey() => ['adult_price' => $target->adult_price, 'child_price' => $target->child_price]],
        ];
        $this->withSession(['customer_id' => $booking->customer_id])
            ->get(route('customer.booking-changes.edit', $booking))->assertOk()
            ->assertSee('name="quoted_prices['.$target->getKey().'][adult_price]"', false);
        $target->update(['adult_price' => 2000000]);
        $this->post(route('customer.booking-changes.update', $booking), $payload)->assertSessionHasErrors('departure_id');
        $this->assertSame($source->getKey(), $booking->fresh()->departure_id);
        $this->assertSame('2000000.00', $booking->fresh()->total_amount);
        $this->assertSame(3, $source->fresh()->available_seats);
        $this->assertSame(5, $target->fresh()->available_seats);
        $this->assertSame(0, $booking->changes()->count());
        $this->get(route('customer.booking-changes.edit', $booking))->assertOk();
        $payload['quoted_prices'][$target->getKey()]['adult_price'] = $target->adult_price;
        $this->post(route('customer.booking-changes.update', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertSame('4000000.00', $booking->fresh()->total_amount);
        $this->assertSame(1, $booking->changes()->count());
    }

    public function test_change_request_cannot_omit_the_displayed_target_price(): void
    {
        $booking = Booking::factory()->create();
        $target = TourDeparture::factory()->create();
        $this->withSession(['customer_id' => $booking->customer_id])
            ->post(route('customer.booking-changes.update', $booking), [
                'departure_id' => $target->getKey(), 'expected_departure_id' => $booking->departure_id,
                'reason' => 'Đổi lịch', 'accept_price' => 1,
            ])->assertSessionHasErrors('quoted_prices');
        $this->assertSame(0, $booking->changes()->count());
    }

    public function test_change_rejects_departures_that_have_started_or_finished_even_when_still_open(): void
    {
        foreach (['in_progress', 'completed'] as $status) {
            foreach (['source', 'target'] as $side) {
                $source = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 3]);
                $target = TourDeparture::factory()->create(['max_seats' => 5, 'available_seats' => 5]);
                $booking = Booking::factory()->create(['departure_id' => $source->getKey(), 'total_adults' => 2]);
                ($side === 'source' ? $source : $target)->forceFill(['operation_status' => $status])->save();
                $this->withSession(['customer_id' => $booking->customer_id])
                    ->get(route('customer.booking-changes.edit', $booking))->assertOk()
                    ->assertViewHas('departures', fn ($departures) => $side !== 'target' || ! $departures->contains('departure_id', $target->getKey()));
                $this->post(route('customer.booking-changes.update', $booking), [
                    'departure_id' => $target->getKey(), 'expected_departure_id' => $source->getKey(),
                    'reason' => 'Đổi lịch', 'accept_price' => 1,
                    'quoted_prices' => [$target->getKey() => ['adult_price' => $target->adult_price, 'child_price' => $target->child_price]],
                ])->assertSessionHasErrors('departure_id');
                $this->assertSame($source->getKey(), $booking->fresh()->departure_id);
                $this->assertSame(3, $source->fresh()->available_seats);
                $this->assertSame(5, $target->fresh()->available_seats);
                $this->assertSame(0, $booking->changes()->count());
            }
        }
    }
}
