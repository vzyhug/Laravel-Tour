<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentIntent;
use App\Services\Payments\PaymentWorkflow;
use App\Services\Payments\SePaySandbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SePaySandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.checkout_methods' => ['sepay', 'vietqr_demo', 'bank_transfer', 'momo', 'cash', 'card']]);
        config(['payments.qr_lifetime_minutes' => 10]);
        $this->withoutVite();
        config(['payments.sepay_sandbox' => ['enabled' => true, 'merchant_id' => 'TEST-MERCHANT', 'secret_key' => 'test-checkout-secret']]);
        Http::preventStrayRequests();
    }

    private function fakeApi(array $responses): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
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
        $response->assertRedirect(route('payments.sepay.checkout', [$booking, $intent]));

        return $intent;
    }

    private function order(PaymentIntent $intent): array
    {
        return ['order_id' => 'PAY-'.$intent->reference, 'order_invoice_number' => $intent->reference,
            'order_status' => 'CAPTURED', 'order_amount' => '1500000.00', 'order_currency' => 'VND', 'transactions' => []];
    }

    private function paymentState(Booking $booking, PaymentIntent $intent)
    {
        return $this->getJson(route('payments.sepay.status', [$booking, $intent]));
    }

    public function test_direct_signed_checkout_uses_server_total_without_local_callbacks_tunnel_or_ipn(): void
    {
        $this->assertTrue(SePaySandbox::ready());
        $booking = $this->booking();
        $intent = $this->start($booking);
        $fields = app(SePaySandbox::class)->fields($intent);
        $signature = $fields['signature'];
        unset($fields['signature']);
        $canonical = implode(',', array_map(fn ($key, $value) => $key.'='.$value, array_keys($fields), array_values($fields)));
        $this->assertSame(base64_encode(hash_hmac('sha256', $canonical, 'test-checkout-secret', true)), $signature);
        $this->assertSame('1500000', $fields['order_amount']);
        $this->assertSame('BANK_TRANSFER', $fields['payment_method']);
        foreach (['success_url', 'error_url', 'cancel_url'] as $field) {
            $this->assertArrayNotHasKey($field, $fields);
        }
        $this->get(route('payments.sepay.checkout', [$booking, $intent]))->assertOk()->assertSee(SePaySandbox::CHECKOUT_URL)
            ->assertSee('data-sepay-checkout-form', false)->assertDontSee('target="_blank"', false)->assertDontSee('test-checkout-secret')
            ->assertDontSee('ngrok')->assertDontSee('Google Lens')->assertHeader('Cache-Control', 'no-store, private');
        $this->start($booking);
        $this->assertDatabaseCount('payment_intents', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        Http::assertNothingSent();
    }

    public function test_authenticated_api_capture_creates_one_payment_invoice_and_actual_provider_order_reference(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $data = $this->order($intent);
        $this->fakeApi([SePaySandbox::API_URL.$intent->reference => Http::response(['data' => $data])]);
        $this->paymentState($booking, $intent)->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('verification_available', true);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'paid');
        $this->get(route('payments.sepay.show', [$booking, $intent]))->assertOk()->assertSee('Thanh toán thành công');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
        $this->assertDatabaseCount('payment_gateway_events', 1);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('SEPAY-SBX-ORDER-'.$data['order_id'], $booking->fresh()->payment->transaction_code);
        Http::assertSent(fn ($request) => $request->url() === SePaySandbox::API_URL.$intent->reference
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('TEST-MERCHANT:test-checkout-secret')));
        Http::assertSentCount(1);
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee('Hóa đơn dịch vụ du lịch');
        $this->get(route('payments.sepay.checkout', [$booking, $intent]))->assertRedirect(route('payments.sepay.show', [$booking, $intent]));
    }

    public function test_return_query_cannot_confirm_payment_and_missing_provider_order_stays_pending(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response([], 404)]);
        $this->get(route('payments.sepay.show', [$booking, $intent, 'paid' => 1, 'status' => 'success', 'amount' => 1500000]))
            ->assertOk()->assertSee('Đang kiểm tra thanh toán')->assertDontSee('data-payment-qr', false);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'pending')->assertJsonPath('invoice_url', null);
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        Http::assertSentCount(1);
    }

    public function test_api_errors_timeout_redirect_or_bad_response_never_mark_paid_and_can_recover(): void
    {
        foreach ([401, 403, 429, 500, 302, 'timeout', 'malformed', 'wrong_invoice', 'unknown_status'] as $case) {
            $booking = $this->booking();
            $intent = $this->start($booking);
            $order = $this->order($intent);
            if ($case === 'wrong_invoice') {
                $order['order_invoice_number'] = 'OTHER-ORDER';
            }
            if ($case === 'unknown_status') {
                $order['order_status'] = 'UNKNOWN';
            }
            $fake = match ($case) {
                'timeout' => Http::failedConnection(), 'malformed' => Http::response(['data' => ['order_status' => 'CAPTURED']]),
                'wrong_invoice', 'unknown_status' => Http::response(['data' => $order]),
                default => Http::response([], $case, $case === 302 ? ['Location' => 'https://untrusted.example.test'] : []),
            };
            $this->fakeApi([SePaySandbox::API_URL.'*' => $fake]);
            $this->paymentState($booking, $intent)->assertJsonPath('status', 'pending')->assertJsonPath('verification_available', false)->assertJsonPath('invoice_url', null);
            $this->assertSame('pending', $intent->fresh()->status);
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_gateway_events', 0);
        Cache::flush();
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $this->order($intent)])]);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'paid')->assertJsonPath('verification_available', true);
    }

    public function test_pending_cancelled_and_unknown_provider_statuses_are_never_paid(): void
    {
        foreach (['AUTHENTICATION_NOT_NEEDED', 'CANCELLED', 'FAILED'] as $status) {
            $booking = $this->booking();
            $intent = $this->start($booking);
            $data = $this->order($intent);
            $data['order_status'] = $status;
            $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $data])]);
            $this->paymentState($booking, $intent)->assertJsonPath('status', $status === 'CANCELLED' ? 'cancelled' : 'pending')
                ->assertJsonPath('verification_available', $status !== 'FAILED');
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
    }

    public function test_wrong_amount_currency_or_fractional_vnd_requires_review_and_blocks_retry(): void
    {
        foreach ([['order_amount', '1'], ['order_amount', '1500000.01'], ['order_currency', 'USD']] as [$field, $value]) {
            $booking = $this->booking();
            $intent = $this->start($booking);
            $data = $this->order($intent);
            $data[$field] = $value;
            $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $data])]);
            $this->paymentState($booking, $intent)->assertJsonPath('status', 'review')->assertJsonPath('can_retry', false);
            foreach (['sepay', 'vietqr_demo', 'cash'] as $method) {
                $this->post(route('payments.store', $booking), ['payment_method' => $method])->assertConflict();
            }
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
        $this->assertDatabaseCount('payment_gateway_events', 3);
    }

    public function test_late_capture_never_reopens_expired_cancelled_changed_or_otherwise_paid_booking(): void
    {
        $this->freezeTime();
        foreach (['expiry', 'cancel', 'amount', 'other_payment'] as $case) {
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
                $this->post(route('payments.store', $booking), ['payment_method' => 'cash', 'demo_outcome' => 'succeeded'])->assertRedirect();
            }
            $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $this->order($intent)])]);
            $this->paymentState($booking, $intent)->assertJsonPath('status', 'review');
            if ($case === 'cancel') {
                $this->assertSame('cancelled', $booking->fresh()->status);
            }
        }
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
    }

    public function test_customer_ownership_binding_login_production_guard_and_missing_configuration(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->withSession(['customer_id' => Customer::factory()->create()->getKey()]);
        $this->get(route('payments.sepay.checkout', [$booking, $intent]))->assertForbidden();
        $this->get(route('payments.sepay.show', [$booking, $intent]))->assertForbidden();
        $this->paymentState($booking, $intent)->assertForbidden();
        $other = $this->booking();
        $this->get(route('payments.sepay.show', [$other, $intent]))->assertNotFound();
        $this->flushSession();
        $this->get(route('payments.sepay.checkout', [$booking, $intent]))->assertRedirect(route('login'));
        $this->withSession(['customer_id' => $booking->customer_id]);
        config(['payments.sepay_sandbox.secret_key' => '']);
        $this->assertFalse(SePaySandbox::ready());
        $this->post(route('payments.store', $booking), ['payment_method' => 'sepay'])->assertServiceUnavailable();
        $this->paymentState($booking, $intent)->assertJsonPath('verification_available', false);
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertFalse(SePaySandbox::enabled());
        $this->get(route('payments.sepay.show', [$booking, $intent]))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_lookups_are_shared_across_tabs_and_retry_after_five_seconds(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response([], 404)]);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'pending');
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'pending');
        Http::assertSentCount(1);
        $this->travel(6)->seconds();
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'pending');
        Http::assertSentCount(2);
    }

    public function test_expired_attempt_gets_new_reference_and_provider_order_cannot_pay_two_bookings(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->travelTo($intent->expires_at);
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response([], 404)]);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'expired')->assertJsonPath('can_retry', true);
        $new = $this->start($booking);
        $this->assertNotSame($intent->reference, $new->reference);
        $data = $this->order($new);
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $data])]);
        $this->paymentState($booking, $new)->assertJsonPath('status', 'paid');
        $other = $this->booking();
        $otherIntent = $this->start($other);
        $data['order_invoice_number'] = $otherIntent->reference;
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $data])]);
        $this->paymentState($other, $otherIntent)->assertConflict();
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_review_cannot_be_bypassed_through_an_older_demo_qr_page(): void
    {
        $booking = $this->booking();
        $this->post(route('payments.store', $booking), ['payment_method' => 'vietqr_demo'])->assertRedirect();
        $demo = $booking->paymentIntents()->firstOrFail();
        $intent = $this->start($booking);
        $data = $this->order($intent);
        $data['order_amount'] = '1';
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $data])]);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'review');
        $this->get(route('payments.qr.show', [$booking, $demo]))->assertOk()->assertSee('Giao dịch cần kiểm tra');
        $this->getJson(route('payments.qr.status', [$booking, $demo]))->assertJsonPath('status', 'review')->assertJsonPath('can_retry', false);
    }

    public function test_sepay_reuses_a_failed_payment_and_clears_the_new_main_booking_hold(): void
    {
        $booking = $this->booking();
        $booking->update(['hold_expires_at' => now()->addMinutes(30)]);
        $failed = app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'failed');
        $paymentId = $failed->payment_id;
        $intent = $this->start($booking);
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $this->order($intent)])]);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'paid');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($paymentId, $booking->fresh()->payment->getKey());
        $this->assertSame('completed', $booking->fresh()->payment->status);
        $this->assertNull($booking->fresh()->hold_expires_at);
        $this->assertDatabaseCount('bill', 1);
    }

    public function test_pending_momo_vnpay_or_mock_attempt_blocks_a_new_sepay_checkout(): void
    {
        foreach (['mock', 'momo', 'vnpay'] as $provider) {
            $booking = $this->booking();
            $attempt = app(PaymentWorkflow::class)->pay($booking, 'card', (string) Str::uuid(), 'pending');
            $attempt->update(['provider' => $provider]);
            $this->post(route('payments.store', $booking), ['payment_method' => 'sepay'])->assertConflict();
            $this->assertSame('pending', $attempt->fresh()->status);
        }
        $this->assertDatabaseCount('payment_intents', 0);
        $this->assertDatabaseCount('bill', 0);
        Http::assertNothingSent();
    }

    public function test_hold_expiry_before_qr_expiry_releases_seats_once_and_reviews_a_late_capture(): void
    {
        $this->freezeTime();
        $booking = $this->booking();
        $booking->update(['hold_expires_at' => now()->addMinute()]);
        $before = $booking->departure->available_seats;
        $expected = min($booking->departure->max_seats, $before + $booking->total_adults + $booking->total_children);
        $intent = $this->start($booking);
        $this->travel(2)->minutes();
        $this->assertTrue($intent->expires_at->isFuture());
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $this->order($intent)])]);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'review')->assertJsonPath('can_retry', false);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'review');
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame($expected, $booking->departure->fresh()->available_seats);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
    }

    public function test_departure_cancelled_before_capture_is_not_confirmed(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $booking->departure->update(['status' => 'cancelled']);
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $this->order($intent)])]);
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'review');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('bill', 0);
    }

    public function test_database_failure_rolls_back_receipt_payment_invoice_and_releases_lookup_lock(): void
    {
        $booking = $this->booking();
        $intent = $this->start($booking);
        $this->fakeApi([SePaySandbox::API_URL.'*' => Http::response(['data' => $this->order($intent)])]);
        Bill::creating(fn () => throw new RuntimeException('sandbox simulated database failure'));
        $this->withoutExceptionHandling();
        try {
            $this->paymentState($booking, $intent);
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
        $this->paymentState($booking, $intent)->assertJsonPath('status', 'paid');
    }
}
