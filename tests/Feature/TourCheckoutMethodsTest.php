<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Services\Payments\SePaySandbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TourCheckoutMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Do not override checkout_methods: verify the actual default storefront policy.
        config(['payments.sepay_sandbox' => ['enabled' => true, 'merchant_id' => 'TEST-MERCHANT', 'secret_key' => 'test-checkout-secret'],
            'payments.demo_enabled' => true, 'payments.vnpay.enabled' => true, 'payments.momo.enabled' => true]);
        Http::preventStrayRequests();
    }

    private function booking(): Booking
    {
        $booking = Booking::factory()->create(['hold_expires_at' => now()->addMinutes(30)]);
        $this->withSession(['customer_id' => $booking->customer_id]);

        return $booking;
    }

    public function test_storefront_restores_demo_methods_and_excludes_only_vnpay_and_momo(): void
    {
        $booking = $this->booking();
        $this->assertSame(['sepay', 'cash', 'card', 'vietqr_demo'], config('payments.checkout_methods'));
        $page = $this->get(route('payments.create', $booking))->assertOk()
            ->assertSee('QR ngân hàng · SePay')->assertSee('name="payment_method" value="sepay"', false)
            ->assertDontSee('VNPay')->assertDontSee('MoMo')->assertSee('VietQR')
            ->assertSee('Thanh toán tại văn phòng')->assertSee('Thẻ tín dụng')->assertSee('demo_outcome');
        $this->assertSame(4, preg_match_all('/<input\b[^>]*\bname="payment_method"/', $page->getContent()));
        $this->assertMatchesRegularExpression('/name="payment_method" value="sepay"[^>]*checked/', $page->getContent());
    }

    public function test_removed_methods_cannot_create_any_payment_intent_or_attempt(): void
    {
        $booking = $this->booking();
        foreach (['bank_transfer', 'momo'] as $method) {
            $this->postJson(route('payments.store', $booking), ['payment_method' => $method, 'demo_outcome' => 'succeeded', 'idempotency_key' => (string) Str::uuid()])
                ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_intents', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_sepay_checkout_keeps_server_total_and_reuses_the_existing_intent(): void
    {
        $booking = $this->booking();
        $this->post(route('payments.store', $booking), ['payment_method' => 'sepay', 'amount' => 1, 'demo_outcome' => 'succeeded'])->assertRedirect();
        $intent = $booking->paymentIntents()->firstOrFail();
        $this->assertSame($booking->total_amount, $intent->amount);
        $this->get(route('payments.sepay.checkout', [$booking, $intent]))->assertOk()->assertSee(SePaySandbox::CHECKOUT_URL, false);
        $this->post(route('payments.store', $booking), ['payment_method' => 'sepay'])->assertRedirect(route('payments.sepay.checkout', [$booking, $intent]));
        $this->assertDatabaseCount('payment_intents', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->get(route('payments.create', $booking))->assertOk()->assertSee('Tiếp tục thanh toán SePay');
    }

    public function test_unconfigured_sepay_does_not_disable_the_restored_demo_methods(): void
    {
        $booking = $this->booking();
        config(['payments.sepay_sandbox.secret_key' => '']);
        $this->get(route('payments.create', $booking))->assertOk()->assertSee('SePay hiện chưa sẵn sàng')->assertSee('disabled', false)->assertDontSee('MoMo')->assertDontSee('VNPay');
        $this->postJson(route('payments.store', $booking), ['payment_method' => 'sepay'])->assertServiceUnavailable();
        $this->assertDatabaseCount('payment_intents', 0);
    }

    public function test_old_payment_urls_are_disabled_and_pending_legacy_payment_is_not_charged_again(): void
    {
        $booking = $this->booking();
        $payment = $booking->payment()->create(['amount' => $booking->total_amount, 'payment_method' => 'momo', 'status' => 'pending']);
        $attempt = $payment->attempts()->create(['amount' => $booking->total_amount, 'provider' => 'momo', 'status' => 'pending', 'idempotency_key' => (string) Str::uuid()]);
        $momo = $attempt->momoSession()->create(['order_id' => 'OLDMOMO', 'request_id' => 'OLDREQUEST', 'amount' => (int) $booking->total_amount, 'expires_at' => now()->addMinutes(15)]);
        $vnpay = $attempt->vnpaySession()->create(['txn_ref' => 'OLDVNPAY', 'amount_vnd' => (int) $booking->total_amount, 'vnp_amount' => (int) $booking->total_amount * 100, 'payment_url' => 'https://sandbox.vnpayment.vn/']);
        $intent = $booking->paymentIntents()->create(['provider' => 'vietqr_demo', 'reference' => 'DEMOOLD', 'amount' => $booking->total_amount, 'currency' => 'VND', 'qr_payload' => 'demo', 'expires_at' => now()->addMinutes(10)]);
        foreach (['show', 'qr', 'status'] as $route) {
            $this->get(route('payments.momo.'.$route, [$booking, $momo]))->assertNotFound();
        }
        $this->get(route('payments.vnpay.resume', [$booking, $vnpay]))->assertNotFound();
        $this->post(route('payments.vnpay.restart', [$booking, $vnpay]))->assertNotFound();
        foreach (['show', 'status', 'image', 'demo'] as $route) {
            $this->get(route('payments.qr.'.$route, [$booking, $intent]))->assertOk();
        }
        $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success'])->assertConflict();
        $this->post(route('payments.demo.resolve', $attempt), ['outcome' => 'succeeded'])->assertForbidden();
        $this->get(route('payments.create', $booking))->assertOk()->assertSee('Giao dịch trước đây đang chờ đối soát')->assertDontSee('Mở phiên MoMo')->assertDontSee('Tiếp tục thanh toán VNPay');
        $this->postJson(route('payments.store', $booking), ['payment_method' => 'sepay'])->assertConflict();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 0);
    }

    public function test_existing_paid_invoice_and_owner_checks_still_work(): void
    {
        $booking = $this->booking();
        $booking->payment()->create(['amount' => $booking->total_amount, 'payment_method' => 'momo', 'status' => 'completed', 'transaction_code' => 'OLDTX']);
        $booking->bill()->create(['bill_number' => 'OLDINV', 'final_amount' => $booking->total_amount]);
        $this->get(route('customer.bookings.invoice', $booking))->assertOk()->assertSee('OLDINV');
        $this->withSession(['customer_id' => Customer::factory()->create()->getKey()])->postJson(route('payments.store', $booking), ['payment_method' => 'sepay'])->assertForbidden();
    }

    public function test_restored_cash_and_card_support_failure_pending_resolution_and_retry(): void
    {
        foreach (['cash', 'card'] as $method) {
            $booking = $this->booking();
            $this->post(route('payments.store', $booking), ['payment_method' => $method, 'demo_outcome' => 'failed'])->assertRedirect();
            $this->assertSame('failed', $booking->payment()->firstOrFail()->status);
            $this->post(route('payments.store', $booking), ['payment_method' => $method, 'demo_outcome' => 'pending'])->assertRedirect();
            $attempt = $booking->payment()->firstOrFail()->attempts()->where('status', 'pending')->firstOrFail();
            $this->get(route('payments.create', $booking))->assertOk()->assertSee('Xác nhận thanh toán');
            $this->post(route('payments.demo.resolve', $attempt), ['outcome' => 'succeeded'])->assertRedirect();
            $this->assertSame('completed', $booking->payment()->firstOrFail()->status);
            $this->assertSame(1, $booking->payment()->count());
            $this->assertSame(1, $booking->bill()->count());
        }
    }

    public function test_restored_vietqr_demo_can_start_resume_and_confirm_payment(): void
    {
        $booking = $this->booking();
        $this->post(route('payments.store', $booking), ['payment_method' => 'vietqr_demo'])->assertRedirect();
        $intent = $booking->paymentIntents()->firstOrFail();
        $this->get(route('payments.create', $booking))->assertOk()->assertSee('Tiếp tục thanh toán QR');
        $this->get(route('customer.bookings.show', $booking))->assertOk()->assertSee('Xem mã VietQR');
        $this->post(route('payments.qr.simulate', [$booking, $intent]), ['outcome' => 'success'])->assertRedirect();
        $this->assertSame('paid', $intent->fresh()->status);
        $this->assertSame('completed', $booking->payment()->firstOrFail()->status);
        $this->assertSame(1, $booking->bill()->count());
    }
}
