<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\MomoPaymentSession;
use App\Models\PaymentAttempt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MomoPaymentGateway
{
    private const PENDING_RESULT_CODES = [1000, 7000, 7002, 9000];

    private const FAILED_RESULT_CODES = [1001, 1002, 1003, 1004, 1005, 1006, 1007, 1017, 1026, 2117, 2140];

    public static function ready(): bool
    {
        $config = config('payments.momo');

        return (bool) ($config['enabled'] ?? false)
            && ! app()->isProduction()
            && filled($config['partner_code'] ?? null)
            && filled($config['access_key'] ?? null)
            && filled($config['secret_key'] ?? null);
    }

    public function start(Booking $booking, string $idempotencyKey): MomoPaymentSession
    {
        abort_unless(self::ready(), 503, 'MoMo Sandbox chưa được cấu hình. Hãy nhập thông tin sandbox vào file .env.');

        [$session, $payload] = DB::transaction(function () use ($booking, $idempotencyKey) {
            $locked = app(PaymentWorkflow::class)->lockBooking($booking->getKey());
            abort_unless(in_array($locked->status, ['pending', 'confirmed'], true), 409, 'Đơn hàng đã đóng.');
            abort_if($locked->holdHasExpired() || $locked->departure()->first()?->status === 'cancelled', 409, 'Đơn hàng đã hết hạn hoặc chuyến đi đã hủy.');

            $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless($existing->payment->booking_id === $locked->getKey(), 409);
                abort_unless($existing->provider === 'momo' && $existing->momoSession, 409);

                $session = $existing->momoSession;

                return [$session, $session->qr_payload ? null : $this->createRequest($locked, $session)];
            }

            $payment = $locked->payment()->lockForUpdate()->first();
            abort_if($payment && in_array($payment->status, ['completed', 'refunded'], true), 409, 'Đơn hàng đã được thanh toán.');
            abort_if($payment && $payment->attempts()->where('status', 'pending')->exists(), 409, 'Đang có giao dịch chờ xử lý.');

            $amount = (float) $locked->total_amount;
            abort_unless($amount >= 1000 && $amount <= 50_000_000 && floor($amount) === $amount, 422, 'MoMo Sandbox yêu cầu số tiền nguyên từ 1.000 đến 50.000.000 đồng.');

            $payment ??= $locked->payment()->create(['amount' => $locked->total_amount, 'payment_method' => 'momo', 'status' => 'pending']);
            $payment->update(['amount' => $locked->total_amount, 'payment_method' => 'momo', 'status' => 'pending']);

            $attempt = $payment->attempts()->create([
                'idempotency_key' => $idempotencyKey,
                'amount' => $locked->total_amount,
                'status' => 'pending',
                'provider' => 'momo',
            ]);
            $orderId = 'VG'.$locked->getKey().Str::upper(Str::random(18));
            $requestId = 'VG'.Str::lower((string) Str::uuid());
            $session = $attempt->momoSession()->create([
                'order_id' => $orderId,
                'request_id' => $requestId,
                'amount' => (int) $amount,
                'expires_at' => now()->addMinutes(max(5, min(30, (int) config('payments.momo.qr_lifetime_minutes', 15)))),
            ]);

            return [$session, $this->createRequest($locked, $session)];
        });

        if ($session->qr_payload) {
            return $session;
        }
        abort_if($payload === null, 409, 'Phiên MoMo đang được đối soát. Không tạo giao dịch trùng.');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post(rtrim(config('payments.momo.endpoint'), '/').'/v2/gateway/api/create', $payload);
            $body = $response->json();

            if (is_array($body) && (int) ($body['resultCode'] ?? -1) !== 0 && $this->verifyCreateResponse($body, $session)) {
                $this->markStartFailed($session, $body);
                throw ValidationException::withMessages(['payment_method' => 'MoMo Sandbox không tạo được mã QR. Kiểm tra thông tin sandbox hoặc thử lại.']);
            }
            if (! $response->successful() || ! is_array($body) || (int) ($body['resultCode'] ?? -1) !== 0 || ! $this->verifyCreateResponse($body, $session)) {
                throw ValidationException::withMessages(['payment_method' => 'MoMo chưa xác nhận việc tạo mã QR. Phiên được giữ để tránh gửi yêu cầu thanh toán trùng.']);
            }

            $qrPayload = $body['qrCodeUrl'] ?? $body['deeplink'] ?? null;
            $isMomoDeepLink = is_string($qrPayload) && str_starts_with($qrPayload, 'momo://');
            $isSandboxUrl = is_string($qrPayload)
                && parse_url($qrPayload, PHP_URL_HOST) === 'test-payment.momo.vn'
                && in_array(parse_url($qrPayload, PHP_URL_SCHEME), ['https', 'http'], true);
            if (! $isMomoDeepLink && ! $isSandboxUrl) {
                $this->markStartFailed($session, $body);
                throw ValidationException::withMessages(['payment_method' => 'MoMo Sandbox không trả về mã QR MoMo hợp lệ.']);
            }

            $session->update([
                'qr_payload' => $qrPayload,
                'pay_url' => $body['payUrl'] ?? null,
                'deeplink' => $body['deeplink'] ?? null,
                'gateway_response' => $this->safeGatewayResponse($body),
            ]);

            return $session->refresh();
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['payment_method' => 'Không nhận được phản hồi từ MoMo Sandbox. Phiên được giữ để đối soát, tránh gửi yêu cầu thanh toán trùng.']);
        }
    }

    public function handleIpn(array $data): void
    {
        abort_unless($this->verifyIpnSignature($data), 403, 'Chữ ký IPN MoMo không hợp lệ.');

        $session = MomoPaymentSession::query()
            ->where('order_id', $data['orderId'] ?? '')
            ->where('request_id', $data['requestId'] ?? '')
            ->firstOrFail();
        abort_unless((string) $session->amount === (string) ($data['amount'] ?? ''), 409, 'Số tiền IPN không khớp.');
        abort_unless(hash_equals((string) config('payments.momo.partner_code'), (string) ($data['partnerCode'] ?? '')), 403);

        $this->applyVerifiedResult(
            $session,
            (int) ($data['resultCode'] ?? -1),
            filled($data['transId'] ?? null) ? (string) $data['transId'] : null,
            $data
        );
    }

    public function refreshStatus(MomoPaymentSession $session): void
    {
        $session->refresh();
        if (! filled(config('payments.momo.secret_key')) || ! filled(config('payments.momo.access_key')) || ! filled(config('payments.momo.partner_code'))) {
            return;
        }
        if ($session->status !== 'pending' || ! $session->qr_payload) {
            return;
        }

        $requestId = 'Q'.Str::lower(str_replace('-', '', (string) Str::uuid()));
        $fields = [
            'accessKey' => config('payments.momo.access_key'),
            'orderId' => $session->order_id,
            'partnerCode' => config('payments.momo.partner_code'),
            'requestId' => $requestId,
        ];
        $payload = [
            'partnerCode' => $fields['partnerCode'],
            'requestId' => $requestId,
            'orderId' => $session->order_id,
            'lang' => 'vi',
            'signature' => $this->signature($fields),
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post(rtrim(config('payments.momo.endpoint'), '/').'/v2/gateway/api/query', $payload);
        } catch (ConnectionException $exception) {
            report($exception);

            return;
        }

        $body = $response->json();
        if (! $response->successful() || ! is_array($body)
            || ! hash_equals((string) config('payments.momo.partner_code'), (string) ($body['partnerCode'] ?? ''))
            || ! hash_equals($session->order_id, (string) ($body['orderId'] ?? ''))
            || (int) ($body['amount'] ?? -1) !== $session->amount) {
            return;
        }

        $resultCode = (int) ($body['resultCode'] ?? -1);
        if ($resultCode !== 0 && ! in_array($resultCode, self::PENDING_RESULT_CODES, true) && ! in_array($resultCode, self::FAILED_RESULT_CODES, true)) {
            return;
        }

        $this->applyVerifiedResult(
            $session,
            $resultCode,
            filled($body['transId'] ?? null) ? (string) $body['transId'] : null,
            $body
        );
    }

    private function applyVerifiedResult(MomoPaymentSession $session, int $resultCode, ?string $transactionId, array $payload): void
    {
        $outcome = in_array($resultCode, self::PENDING_RESULT_CODES, true)
            ? 'pending'
            : ($resultCode === 0 ? 'succeeded' : (in_array($resultCode, self::FAILED_RESULT_CODES, true) ? 'failed' : 'pending'));
        $attempt = $session->attempt;
        if ($session->status !== 'pending') {
            abort_unless($session->status === $outcome, 409, 'MoMo gửi kết quả giao dịch mâu thuẫn.');

            return;
        }
        if ($attempt->status !== 'pending') {
            abort_unless($attempt->status === $outcome, 409, 'MoMo gửi kết quả không khớp với giao dịch đã chốt.');
            $session->update([
                'status' => $outcome,
                'result_code' => $resultCode,
                'transaction_id' => $transactionId,
                'ipn_payload' => $this->safeGatewayResponse($payload),
            ]);

            return;
        }
        $session->update([
            'result_code' => $resultCode,
            'transaction_id' => $transactionId,
            'ipn_payload' => $this->safeGatewayResponse($payload),
        ]);

        if ($outcome === 'pending') {
            return;
        }

        $attempt->refresh();
        app(PaymentWorkflow::class)->settle(
            $attempt,
            $outcome,
            $transactionId ?? $session->order_id
        );
        $session->update(['status' => $outcome]);
    }

    public function verifyIpnSignature(array $data): bool
    {
        if (! filled(config('payments.momo.secret_key')) || ! filled(config('payments.momo.access_key')) || ! filled(config('payments.momo.partner_code'))) {
            return false;
        }
        foreach ($data as $value) {
            if (! is_scalar($value) && $value !== null) {
                return false;
            }
        }
        $fields = [
            'accessKey' => config('payments.momo.access_key'),
            'amount' => $data['amount'] ?? '',
            'extraData' => $data['extraData'] ?? '',
            'message' => $data['message'] ?? '',
            'orderId' => $data['orderId'] ?? '',
            'orderInfo' => $data['orderInfo'] ?? '',
            'orderType' => $data['orderType'] ?? '',
            'partnerCode' => $data['partnerCode'] ?? '',
            'payType' => $data['payType'] ?? '',
            'requestId' => $data['requestId'] ?? '',
            'responseTime' => $data['responseTime'] ?? '',
            'resultCode' => $data['resultCode'] ?? '',
            'transId' => $data['transId'] ?? '',
        ];

        return $this->matchesSignature($fields, (string) ($data['signature'] ?? ''));
    }

    private function createRequest(Booking $booking, MomoPaymentSession $session): array
    {
        $ipnUrl = filled(config('payments.momo.ipn_url'))
            ? config('payments.momo.ipn_url')
            : route('payments.momo.ipn');
        $redirectUrl = route('payments.momo.return', ['booking' => $booking, 'session' => $session]);
        $fields = [
            'accessKey' => config('payments.momo.access_key'),
            'amount' => (string) $session->amount,
            'extraData' => '',
            'ipnUrl' => $ipnUrl,
            'orderId' => $session->order_id,
            'orderInfo' => 'VietGo booking '.$booking->booking_code,
            'partnerCode' => config('payments.momo.partner_code'),
            'redirectUrl' => $redirectUrl,
            'requestId' => $session->request_id,
            'requestType' => 'captureWallet',
        ];

        return [
            'partnerCode' => $fields['partnerCode'],
            'requestId' => $fields['requestId'],
            'amount' => (int) $fields['amount'],
            'orderId' => $fields['orderId'],
            'orderInfo' => $fields['orderInfo'],
            'redirectUrl' => $fields['redirectUrl'],
            'ipnUrl' => $fields['ipnUrl'],
            'requestType' => $fields['requestType'],
            'extraData' => '',
            'lang' => 'vi',
            'autoCapture' => true,
            'signature' => $this->signature($fields),
        ];
    }

    private function verifyCreateResponse(array $body, MomoPaymentSession $session): bool
    {
        $fields = [
            'accessKey' => config('payments.momo.access_key'),
            'amount' => $body['amount'] ?? '',
            'message' => $body['message'] ?? '',
            'orderId' => $body['orderId'] ?? '',
            'partnerCode' => $body['partnerCode'] ?? '',
            'payUrl' => $body['payUrl'] ?? '',
            'requestId' => $body['requestId'] ?? '',
            'responseTime' => $body['responseTime'] ?? '',
            'resultCode' => $body['resultCode'] ?? '',
        ];

        return hash_equals((string) config('payments.momo.partner_code'), (string) ($body['partnerCode'] ?? ''))
            && hash_equals($session->order_id, (string) ($body['orderId'] ?? ''))
            && hash_equals($session->request_id, (string) ($body['requestId'] ?? ''))
            && isset($body['amount'])
            && (int) $body['amount'] === $session->amount
            && $this->matchesSignature($fields, (string) ($body['signature'] ?? ''));
    }

    private function signature(array $fields): string
    {
        ksort($fields);
        $raw = collect($fields)->map(fn ($value, $key) => $key.'='.$value)->implode('&');

        return hash_hmac('sha256', $raw, (string) config('payments.momo.secret_key'));
    }

    private function matchesSignature(array $fields, string $signature): bool
    {
        return $signature !== '' && hash_equals($this->signature($fields), $signature);
    }

    private function markStartFailed(MomoPaymentSession $session, array $response): void
    {
        DB::transaction(function () use ($session, $response) {
            $locked = MomoPaymentSession::query()->lockForUpdate()->findOrFail($session->getKey());
            $locked->update([
                'status' => 'failed',
                'result_code' => isset($response['resultCode']) ? (int) $response['resultCode'] : null,
                'gateway_response' => $this->safeGatewayResponse($response),
            ]);
            $attempt = $locked->attempt;
            $attempt->update(['status' => 'failed']);
            $attempt->payment->update(['status' => 'failed']);
        });
    }

    private function safeGatewayResponse(array $payload): array
    {
        unset($payload['signature'], $payload['accessKey'], $payload['secretKey']);

        return $payload;
    }
}
