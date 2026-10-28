<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentIntent;
use App\Services\Payments\SePaySandbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class SePaySandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['payments.sepay_sandbox' => ['enabled' => true, 'merchant_id' => 'TEST-MERCHANT', 'secret_key' => 'test-checkout-secret',
            'ipn_secret' => 'test-ipn-secret', 'public_url' => 'https://callback.example.test']]);
        Http::preventStrayRequests();
    }

    private function booking(): Booking
    {
        $booking = Booking::factory()->withPassengers()->create(['total_amount' => 1500000, 'status' => 'pending']);
        $this->withSession(['customer_id' => $booking->customer_id]);

        return $booking;
    }

    private function start(Booking $booking): PaymentIntent
    {
        $response = $this->post(route('payments.store', $booking), ['payment_method' => 'sepay', 'amount' => 1]);
        $intent = $booking->paymentIntents()->latest('id')->firstOrFail();
        $response->assertRedirect(route('payments.sepay.show', [$booking, $intent]));

        return $intent;
    }

    private function payload(PaymentIntent $intent): array
    {
        return ['notification_type' => 'ORDER_PAID', 'timestamp' => now()->timestamp,
            'order' => ['order_id' => 'provider-order-1', 'order_status' => 'CAPTURED', 'order_currency' => 'VND', 'order_amount' => '1500000.00', 'order_invoice_number' => $intent->reference],
            'transaction' => ['transaction_id' => 'sandbox-transaction-1', 'payment_method' => 'BANK_TRANSFER', 'transaction_type' => 'PAYMENT',
                'transaction_status' => 'APPROVED', 'transaction_currency' => 'VND', 'transaction_amount' => '1500000']];
    }

    private function ipn(array $data)
    {
        return $this->postJson(route('payments.sepay.ipn'), $data, ['X-Secret-Key' => 'test-ipn-secret']);
    }

    public function test_signed_checkout_uses_server_total_and_does_not_expose_secret_or_confirm_payment(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $fields = app(SePaySandbox::class)->fields($intent);
        $signature = $fields['signature'];
        unset($fields['signature']);
        $canonical = implode(',', array_map(fn ($key, $value) => $key.'='.$value, array_keys($fields), array_values($fields)));
        $this->assertSame(base64_encode(hash_hmac('sha256', $canonical, 'test-checkout-secret', true)), $signature);
        $this->assertSame('1500000', $fields['order_amount']);
        $this->assertSame('BANK_TRANSFER', $fields['payment_method']);
        $this->assertSame('https://callback.example.test/sepay-sandbox/return', $fields['success_url']);
        $this->get(route('payments.sepay.show', [$booking, $intent]))->assertOk()->assertSee(SePaySandbox::CHECKOUT_URL)
            ->assertSee('1.500.000₫')->assertDontSee('test-checkout-secret')->assertDontSee('test-ipn-secret')->assertHeader('Cache-Control', 'no-store, private');
        $this->start($booking);
        $this->assertDatabaseCount('payment_intents', 1);
        $this->get(route('payments.sepay.return', ['status' => 'success', 'paid' => 1]))->assertOk();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_authenticated_provider_ipn_creates_one_payment_invoice_and_polls_paid_without_customer_session(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $data = $this->payload($intent);
        $this->flushSession();
        $this->ipn($data)->assertOk()->assertJsonPath('success', true);
        $this->ipn($data)->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
        $this->assertDatabaseCount('payment_gateway_events', 1);
        $this->assertSame('paid', $intent->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseHas('payments', ['amount' => 1500000, 'transaction_code' => 'SEPAY-SBX-sandbox-transaction-1', 'status' => 'completed']);
        $this->withSession(['customer_id' => $booking->customer_id]);
        $this->getJson(route('payments.sepay.status', [$booking, $intent]))->assertJsonPath('status', 'paid')->assertJsonPath('can_retry', false);
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee('Hóa đơn demo');
    }

    public function test_ipn_rejects_missing_wrong_or_checkout_key_and_invalid_json(): void
    {
        $intent = $this->start($this->booking());
        $data = $this->payload($intent);
        foreach (['', 'wrong-secret', 'test-checkout-secret'] as $secret) {
            $this->postJson(route('payments.sepay.ipn'), $data, ['X-Secret-Key' => $secret])->assertUnauthorized();
        }
        $this->ipn([])->assertUnprocessable();
        $data['order']['order_invoice_number'] = 'UNKNOWN';
        $this->ipn($data)->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_gateway_events', 0);
    }

    public function test_amount_currency_and_provider_status_mismatches_are_saved_for_review_and_block_retry(): void
    {
        foreach ([['order', 'order_amount', '1'], ['transaction', 'transaction_amount', '1'], ['transaction', 'transaction_amount', '1500000.01'],
            ['order', 'order_currency', 'USD'], ['transaction', 'transaction_currency', 'USD'], ['order', 'order_status', 'PENDING'],
            ['transaction', 'transaction_status', 'FAILED'], ['transaction', 'transaction_type', 'REFUND'], ['transaction', 'payment_method', 'CARD']] as $n => [$part, $field, $value]) {
            $booking = $this->booking();
            $intent = $this->start($booking);
            $data = $this->payload($intent);
            $data['transaction']['transaction_id'] .= '-'.$n;
            $data[$part][$field] = $value;
            $this->ipn($data)->assertOk();
            $this->assertSame('review', $intent->fresh()->status);
            $this->getJson(route('payments.sepay.status', [$booking, $intent]))->assertJsonPath('status', 'review')->assertJsonPath('can_retry', false);
            foreach (['sepay', 'bank_transfer', 'cash'] as $method) {
                $this->post(route('payments.store', $booking), ['payment_method' => $method])->assertConflict();
            }
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        $this->assertDatabaseCount('payment_gateway_events', 9);
    }

    public function test_expired_cancelled_or_changed_booking_never_gets_reopened_by_late_ipn(): void
    {
        $this->freezeTime();
        foreach (['expiry', 'cancel', 'amount', 'other_payment'] as $n => $case) {
            $booking = $this->booking();
            $intent = $this->start($booking);
            if ($case === 'expiry') {
                $this->travelTo($intent->expires_at);
            }
            if ($case === 'cancel') {
                $booking->update(['status' => 'cancelled']);
            }
            if ($case === 'amount') {
                $booking->update(['total_amount' => 1600000]);
            }
            if ($case === 'other_payment') {
                $this->post(route('payments.store', $booking), ['payment_method' => 'cash'])->assertRedirect();
            }
            $data = $this->payload($intent);
            $data['transaction']['transaction_id'] .= '-'.$n;
            $this->ipn($data)->assertOk();
            $this->assertSame('review', $intent->fresh()->status);
            if ($case === 'cancel') {
                $this->assertSame('cancelled', $booking->fresh()->status);
            }
        }
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
    }

    public function test_void_does_not_silently_refund_or_erase_a_confirmed_payment(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $data = $this->payload($intent);
        $this->ipn($data)->assertOk();
        $data['notification_type'] = 'TRANSACTION_VOID';
        $data['transaction']['transaction_type'] = 'VOID';
        $this->ipn($data)->assertOk();
        $this->ipn($data)->assertOk();
        $this->assertSame('review', $intent->fresh()->status);
        $this->assertSame('completed', $booking->fresh()->payment->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
        $this->assertDatabaseCount('payment_gateway_events', 2);
    }

    public function test_customer_ownership_binding_production_guard_and_incomplete_configuration(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->withSession(['customer_id' => Customer::factory()->create()->getKey()]);
        $this->get(route('payments.sepay.show', [$booking, $intent]))->assertForbidden();
        $this->getJson(route('payments.sepay.status', [$booking, $intent]))->assertForbidden();
        $other = $this->booking();
        $this->get(route('payments.sepay.show', [$other, $intent]))->assertNotFound();
        $this->flushSession();
        $this->get(route('payments.sepay.show', [$booking, $intent]))->assertRedirect(route('login'));
        $this->withSession(['customer_id' => $booking->customer_id]);
        config(['payments.sepay_sandbox.ipn_secret' => '']);
        $this->assertFalse(SePaySandbox::ready());
        $this->post(route('payments.store', $booking), ['payment_method' => 'sepay'])->assertServiceUnavailable();
        $this->ipn($this->payload($intent))->assertUnauthorized();
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertFalse(SePaySandbox::enabled());
        $this->get(route('payments.sepay.show', [$booking, $intent]))->assertNotFound();
        $this->postJson(route('payments.sepay.ipn'), $this->payload($intent))->assertNotFound();
    }

    public function test_expiry_has_a_new_reference_and_duplicate_transaction_cannot_pay_another_booking(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->travelTo($intent->expires_at);
        $this->getJson(route('payments.sepay.status', [$booking, $intent]))->assertJsonPath('status', 'expired')->assertJsonPath('can_retry', true);
        $new = $this->start($booking);
        $this->assertNotSame($intent->reference, $new->reference);
        $this->ipn($this->payload($new))->assertOk();
        $other = $this->booking();
        $otherIntent = $this->start($other);
        $this->ipn($this->payload($otherIntent))->assertConflict();
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_review_cannot_be_bypassed_through_an_older_demo_qr_page(): void
    {
        $booking = $this->booking();
        $this->post(route('payments.store', $booking), ['payment_method' => 'bank_transfer'])->assertRedirect();
        $demo = $booking->paymentIntents()->firstOrFail();
        $intent = $this->start($booking);
        $data = $this->payload($intent);
        $data['order']['order_amount'] = '1';
        $this->ipn($data)->assertOk();
        $this->get(route('payments.qr.show', [$booking, $demo]))->assertOk()->assertSee('Giao dịch cần kiểm tra');
        $this->getJson(route('payments.qr.status', [$booking, $demo]))->assertJsonPath('status', 'review')->assertJsonPath('can_retry', false);
    }

    public function test_phone_handoff_requires_an_unexpired_signature_and_shows_no_customer_personal_data(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $url = URL::temporarySignedRoute('payments.sepay.phone', $intent->expires_at, ['intent' => $intent], absolute: false);
        $this->get(route('payments.sepay.phone-image', [$booking, $intent]))->assertOk()->assertSee('<svg', false);
        $this->flushSession();
        $this->get($url)->assertOk()->assertSee('Mở SePay Sandbox')->assertDontSee($booking->customer->email)
            ->assertDontSee('test-checkout-secret')->assertDontSee('test-ipn-secret');
        $this->get(route('payments.sepay.phone', $intent))->assertForbidden();
        $this->get($url.'tampered')->assertForbidden();
        $this->travelTo($intent->expires_at->addSecond());
        $this->get($url)->assertForbidden();
        $freshUrl = URL::temporarySignedRoute('payments.sepay.phone', now()->addMinute(), ['intent' => $intent], absolute: false);
        $this->get($freshUrl)->assertGone();
    }

    public function test_database_failure_rolls_back_the_event_payment_invoice_and_booking(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        Bill::creating(fn () => throw new RuntimeException('sandbox simulated database failure'));
        $this->withoutExceptionHandling();
        try {
            $this->ipn($this->payload($intent));
            $this->fail('Expected rollback');
        } catch (RuntimeException $e) {
            $this->assertSame('sandbox simulated database failure', $e->getMessage());
        } finally {
            Bill::flushEventListeners();
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        $this->assertDatabaseCount('payment_gateway_events', 0);
        $this->assertSame('pending', $intent->fresh()->status);
        $this->assertSame('pending', $booking->fresh()->status);
    }
}
