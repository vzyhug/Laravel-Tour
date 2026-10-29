<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\MomoPaymentSession;
use App\Models\VnpayPaymentSession;
use App\Services\Payments\VnpayPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPaymentGatewayValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        config(['payments.vnpay.enabled' => true, 'payments.vnpay.tmn_code' => 'TESTONLY', 'payments.vnpay.hash_secret' => 'test-only-key', 'payments.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'payments.momo.partner_code' => 'TESTONLY', 'payments.momo.access_key' => 'test-only-access', 'payments.momo.secret_key' => 'test-only-key']);
    }

    private function booking(): Booking
    {
        $customer = Customer::factory()->create();
        $this->withSession(['customer_id' => $customer->getKey()]);

        return Booking::factory()->for($customer)->create(['total_amount' => 1000000, 'hold_expires_at' => now()->addMinutes(30)]);
    }

    private function vnpaySignature(array $data, string $key = 'test-only-key'): array
    {
        unset($data['vnp_SecureHash']);
        $signed = array_filter($data, fn ($value) => $value !== '' && $value !== null);
        ksort($signed);

        return [...$data, 'vnp_SecureHash' => hash_hmac('sha512', http_build_query($signed, '', '&', PHP_QUERY_RFC1738), $key)];
    }

    private function momoSignature(array $data, string $key = 'test-only-key', string $access = 'test-only-access'): array
    {
        $fields = ['accessKey' => $access];
        foreach (['amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'] as $field) {
            $fields[$field] = $data[$field] ?? '';
        }
        ksort($fields);
        $raw = implode('&', array_map(fn ($field) => $field.'='.$fields[$field], array_keys($fields)));

        return [...$data, 'signature' => hash_hmac('sha256', $raw, $key)];
    }

    public function test_vnpay_rejects_forged_and_wrong_amount_callbacks_and_settles_once_with_valid_signature(): void
    {
        $booking = $this->booking();
        app(VnpayPaymentGateway::class)->start($booking, (string) Str::uuid(), '127.0.0.1');
        $session = VnpayPaymentSession::firstOrFail();
        $data = ['vnp_TxnRef' => $session->txn_ref, 'vnp_TmnCode' => 'TESTONLY', 'vnp_Amount' => (string) $session->vnp_amount, 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_TransactionNo' => 'TESTTX123'];
        $this->getJson(route('payments.vnpay.return', [...$data, 'vnp_SecureHash' => 'forged']))->assertForbidden();
        $this->getJson(route('payments.vnpay.return', $this->vnpaySignature([...$data, 'vnp_Amount' => '1'])))->assertConflict();
        $this->assertSame('pending', $booking->payment()->firstOrFail()->status);
        $this->assertDatabaseCount('bill', 0);
        $other = Customer::factory()->create();
        $this->withSession(['customer_id' => $other->getKey()])->getJson(route('payments.vnpay.return', $this->vnpaySignature($data)))->assertForbidden();
        $this->withSession(['customer_id' => $booking->customer_id]);
        for ($i = 0; $i < 2; $i++) {
            $this->get(route('payments.vnpay.return', $this->vnpaySignature($data)))->assertRedirect(route('customer.bookings.show', $booking));
        }
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bill', 1);
    }

    public function test_vnpay_cannot_verify_a_callback_using_an_empty_configuration_secret(): void
    {
        $booking = $this->booking();
        app(VnpayPaymentGateway::class)->start($booking, (string) Str::uuid(), '127.0.0.1');
        $session = VnpayPaymentSession::firstOrFail();
        config(['payments.vnpay.hash_secret' => '', 'payments.vnpay.tmn_code' => '']);
        $data = ['vnp_TxnRef' => $session->txn_ref, 'vnp_TmnCode' => '', 'vnp_Amount' => (string) $session->vnp_amount, 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00'];
        $this->getJson(route('payments.vnpay.return', $this->vnpaySignature($data, '')))->assertStatus(503);
        $this->assertDatabaseCount('bill', 0);
    }

    private function momoSession(Booking $booking): MomoPaymentSession
    {
        $payment = $booking->payment()->create(['amount' => 1000000, 'payment_method' => 'momo', 'status' => 'pending']);
        $attempt = $payment->attempts()->create(['idempotency_key' => (string) Str::uuid(), 'amount' => 1000000, 'status' => 'pending', 'provider' => 'momo']);

        return $attempt->momoSession()->create(['order_id' => 'AUDITORDER', 'request_id' => 'AUDITREQUEST', 'amount' => 1000000, 'expires_at' => now()->addMinutes(15)]);
    }

    public function test_momo_rejects_invalid_shapes_signatures_and_amounts_and_processes_valid_ipn_once(): void
    {
        $booking = $this->booking();
        $session = $this->momoSession($booking);
        $data = ['orderId' => $session->order_id, 'requestId' => $session->request_id, 'partnerCode' => 'TESTONLY', 'amount' => 1000000, 'resultCode' => 0, 'transId' => 'TESTTX123'];
        foreach ([['signature' => ['bad']], ['amount' => [1000000]], ['signature' => 'forged']] as $change) {
            $this->postJson(route('payments.momo.ipn'), array_replace($data, $change))->assertStatus(400);
        }
        $this->postJson(route('payments.momo.ipn'), $this->momoSignature([...$data, 'amount' => 1]))->assertStatus(400);
        $this->assertDatabaseCount('bill', 0);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('payments.momo.ipn'), $this->momoSignature($data))->assertOk()->assertJsonPath('resultCode', 0);
        }
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('bill', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_momo_rejects_an_ipn_signed_with_empty_configuration_secrets(): void
    {
        $booking = $this->booking();
        $session = $this->momoSession($booking);
        config(['payments.momo.partner_code' => '', 'payments.momo.access_key' => '', 'payments.momo.secret_key' => '']);
        $data = ['orderId' => $session->order_id, 'requestId' => $session->request_id, 'partnerCode' => '', 'amount' => 1000000, 'resultCode' => 0];
        $this->postJson(route('payments.momo.ipn'), $this->momoSignature($data, '', ''))->assertStatus(400);
        $this->assertDatabaseCount('bill', 0);
    }
}
