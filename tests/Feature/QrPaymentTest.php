<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentIntent;
use App\Models\TourDeparture;
use App\Services\Payments\VietQrPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class QrPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['payments.demo_enabled' => true, 'payments.qr_lifetime_minutes' => 10]);
        Http::preventStrayRequests();
    }

    private function booking(): Booking
    {
        $booking = Booking::factory()->withPassengers()->create([
            'departure_id' => TourDeparture::factory()->create(['departure_date' => today()->addDays(20), 'adult_price' => 775000, 'available_seats' => 18, 'max_seats' => 20]),
            'booking_date' => now(), 'total_adults' => 2, 'total_amount' => 1500000, 'discount_amount' => 50000,
        ]);
        $this->withSession(['customer_id' => $booking->customer_id]);

        return $booking;
    }

    private function start(Booking $booking): PaymentIntent
    {
        $response = $this->post(route('payments.store', $booking), ['payment_method' => 'bank_transfer', 'amount' => 1, 'total_amount' => 1]);
        $intent = $booking->paymentIntents()->latest('id')->firstOrFail();
        $response->assertRedirect(route('payments.qr.show', [$booking, $intent]));

        return $intent;
    }

    public function test_payload_matches_an_independent_published_vietqr_vector(): void
    {
        // Fixture: https://github.com/khoativi/dynamic-vietqr#example
        $this->assertSame('00020101021238570010A00000072701270006970403011300110123456780208QRIBFTTA530370454061800005802VN62230819thanh toan don hang63045FAB',
            app(VietQrPayload::class)->generate('970403', '0011012345678', '180000', 'thanh toan don hang'));
    }

    public function test_qr_uses_the_locked_booking_total_and_does_not_mark_it_paid_when_created_or_viewed(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->assertSame('1500000.00', $intent->amount);
        $this->assertStringContainsString('530370454071500000', $intent->qr_payload);
        $this->assertStringContainsString('0006000000', $intent->qr_payload);
        $this->assertStringContainsString('VIETGODEMO', $intent->qr_payload);
        $this->assertStringContainsString($intent->reference, $intent->qr_payload);
        $this->get(route('payments.qr.show', [$booking, $intent]))->assertOk()->assertSee('1.500.000₫')->assertSee('Chế độ demo học tập')
            ->assertSee('data-qr-payment', false)->assertSee('Ngân hàng mô phỏng')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('payments.qr.demo', [$booking, $intent]))->assertOk()->assertSee('Mô phỏng thanh toán thành công');
        $this->get(route('payments.qr.image', [$booking, $intent]))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
        $this->getJson(route('payments.qr.status', [$booking, $intent]))->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('invoice_url', null);
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        $this->start($booking);
        $this->assertDatabaseCount('payment_intents', 1);
        $this->assertSame(18, $booking->departure->fresh()->available_seats);
        $this->get(route('customer.bookings.show', $booking))->assertOk();
    }

    public function test_demo_success_creates_one_payment_and_invoice_and_replays_do_not_charge_twice(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        foreach (['success', 'success', 'failed'] as $outcome) {
            $this->post(route('payments.qr.simulate', [$booking, $intent]), compact('outcome'))->assertRedirect(route('payments.qr.demo', [$booking, $intent]));
        }
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->getKey(), 'amount' => 1500000, 'status' => 'completed', 'transaction_code' => 'DEMO-'.$intent->id]);
        $this->assertDatabaseHas('bill', ['booking_id' => $booking->getKey(), 'final_amount' => 1500000]);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('paid', $intent->fresh()->status);
        $this->assertNotNull($intent->fresh()->paid_at);
        $this->assertSame(18, $booking->departure->fresh()->available_seats);
        $this->getJson(route('payments.qr.status', [$booking, $intent]))->assertOk()->assertJsonPath('status', 'paid')
            ->assertJsonPath('invoice_url', route('customer.bookings.invoice', $booking))->assertJsonPath('can_retry', false);
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee('1.500.000')->assertSee('Hóa đơn demo');
        $this->get(route('payments.qr.image', [$booking, $intent]))->assertGone();
        $this->post(route('payments.store', $booking), ['payment_method' => 'bank_transfer'])->assertConflict();
    }

    public function test_failure_and_expiry_allow_new_qr_but_old_attempts_cannot_be_revived(): void
    {
        $booking = $this->booking();
        foreach (['failed', 'expired'] as $outcome) {
            $intent = $this->start($booking);
            $this->post(route('payments.qr.simulate', [$booking, $intent]), compact('outcome'))->assertRedirect();
            $this->assertSame($outcome, $intent->fresh()->status);
            $this->getJson(route('payments.qr.status', [$booking, $intent]))->assertOk()->assertJsonPath('status', $outcome)->assertJsonPath('can_retry', true);
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('bill', 0);
            $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success'])->assertConflict();
        }
        $latest = $this->start($booking);
        $this->assertNotSame($intent->reference, $latest->reference);
        $this->assertNotSame($intent->qr_payload, $latest->qr_payload);
        $this->assertDatabaseCount('payment_intents', 3);
    }

    public function test_actual_expiry_at_the_exact_deadline_cannot_confirm_payment(): void
    {
        $this->freezeTime();
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->travelTo($intent->expires_at);
        $this->getJson(route('payments.qr.status', [$booking, $intent]))->assertOk()->assertJsonPath('status', 'expired')->assertJsonPath('seconds_remaining', 0);
        $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success'])->assertRedirect();
        $this->assertSame('expired', $intent->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        $new = $this->start($booking);
        $this->assertNotSame($intent->id, $new->id);
    }

    public function test_changed_total_invalidates_old_qr_and_new_qr_uses_the_new_server_amount(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $booking->update(['total_amount' => 1600000]);
        $this->getJson(route('payments.qr.status', [$booking, $intent]))->assertOk()->assertJsonPath('status', 'cancelled');
        $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success'])->assertRedirect();
        $this->assertSame('cancelled', $intent->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('1600000.00', $this->start($booking)->amount);
    }

    public function test_a_customer_cannot_submit_an_amount_or_reference_to_the_simulator(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->postJson(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success', 'amount' => 1, 'reference' => 'OTHER', 'booking_id' => 999])->assertUnprocessable();
        $this->assertSame('pending', $intent->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_qr_routes_enforce_owner_booking_binding_login_and_simulator_input(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $otherBooking = Booking::factory()->create();
        $this->get(route('payments.qr.show', [$booking, $otherBooking->paymentIntents()->create([
            'reference' => 'OTHER', 'qr_payload' => 'demo', 'amount' => 10, 'expires_at' => now()->addMinutes(10),
        ])]))->assertNotFound();
        $this->postJson(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'paid'])->assertUnprocessable();
        $this->withSession(['customer_id' => Customer::factory()->create()->getKey()]);
        foreach (['show', 'status', 'image', 'demo'] as $route) {
            $this->get(route('payments.qr.'.$route, [$booking, $intent]))->assertForbidden();
        }
        $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success'])->assertForbidden();
        $this->flushSession();
        $this->get(route('payments.qr.show', [$booking, $intent]))->assertRedirect(route('login'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_demo_routes_are_disabled_by_flag_and_always_disabled_in_production(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->withSession(['_token' => 'qr-demo-test']);
        foreach ([false, true] as $enabled) {
            config(['payments.demo_enabled' => $enabled]);
            if ($enabled) {
                $this->app->instance('env', 'production');
            }
            foreach (['show', 'status', 'image', 'demo'] as $route) {
                $this->get(route('payments.qr.'.$route, [$booking, $intent]))->assertNotFound();
            }
            $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success', '_token' => 'qr-demo-test'])->assertNotFound();
            $this->post(route('payments.store', $booking), ['payment_method' => 'bank_transfer', '_token' => 'qr-demo-test'])->assertNotFound();
        }
        $this->app->instance('env', 'testing');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelling_a_pending_booking_invalidates_its_qr_and_late_success_cannot_resurrect_it(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->post(route('customer.bookings.cancel', $booking), ['cancel_reason' => 'Đổi kế hoạch demo'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('cancelled', $intent->fresh()->status);
        $this->getJson(route('payments.qr.status', [$booking, $intent]))->assertJsonPath('status', 'cancelled')->assertJsonPath('can_retry', false);
        $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success'])->assertConflict();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(20, $booking->departure->fresh()->available_seats);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
    }

    public function test_payment_by_another_method_closes_the_old_qr_without_creating_a_second_payment(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->post(route('payments.store', $booking), ['payment_method' => 'cash'])->assertRedirect(route('customer.bookings.show', $booking));
        $this->getJson(route('payments.qr.status', [$booking, $intent]))->assertJsonPath('status', 'superseded')->assertJsonPath('can_retry', false);
        $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success'])->assertConflict();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
    }

    public function test_invoice_failure_rolls_back_payment_booking_and_intent_together(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        Bill::creating(fn () => throw new RuntimeException('Invoice storage failed'));
        try {
            $this->withoutExceptionHandling()->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success']);
            $this->fail('An invoice database failure should abort the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Invoice storage failed', $exception->getMessage());
        } finally {
            Bill::flushEventListeners();
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame('pending', $intent->fresh()->status);
    }
}
