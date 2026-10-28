<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\VnpayPaymentSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VnpayPaymentGateway
{
    public static function ready(): bool
    {
        $config = config('payments.vnpay');

        return (bool) ($config['enabled'] ?? false)
            && ! app()->isProduction()
            && filled($config['tmn_code'] ?? null)
            && filled($config['hash_secret'] ?? null)
            && filter_var($config['url'] ?? null, FILTER_VALIDATE_URL)
            && parse_url($config['url'], PHP_URL_HOST) === 'sandbox.vnpayment.vn';
    }

    public function start(Booking $booking, string $idempotencyKey, string $ipAddress): string
    {
        abort_unless(self::ready(), 503, 'VNPay Sandbox chưa được cấu hình trong file .env.');

        return DB::transaction(function () use ($booking, $idempotencyKey, $ipAddress) {
            $locked = app(PaymentWorkflow::class)->lockBooking($booking->getKey());
            abort_unless(in_array($locked->status, ['pending', 'confirmed'], true), 409, 'Đơn hàng đã đóng.');
            abort_if($locked->holdHasExpired() || $locked->departure->status === 'cancelled', 409, 'Đơn hàng đã hết hạn hoặc chuyến đi đã hủy.');

            $existing = PaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless($existing->payment->booking_id === $locked->getKey(), 409);
                abort_unless($existing->provider === 'vnpay' && $existing->vnpaySession, 409);

                return $existing->vnpaySession->payment_url;
            }

            $payment = $locked->payment()->lockForUpdate()->first();
            abort_if($payment && in_array($payment->status, ['completed', 'refunded'], true), 409, 'Đơn hàng đã được thanh toán.');
            abort_if($payment && $payment->attempts()->where('status', 'pending')->exists(), 409, 'Đang có giao dịch chờ xử lý.');

            $amount = (float) $locked->total_amount;
            abort_unless($amount > 0 && $amount <= 99_999_999 && floor($amount) === $amount, 422, 'Số tiền VNPay phải là số nguyên VND hợp lệ.');

            $payment ??= $locked->payment()->create([
                'amount' => $locked->total_amount,
                'payment_method' => 'bank_transfer',
                'status' => 'pending',
            ]);
            $payment->update([
                'amount' => $locked->total_amount,
                'payment_method' => 'bank_transfer',
                'status' => 'pending',
            ]);

            $attempt = $payment->attempts()->create([
                'idempotency_key' => $idempotencyKey,
                'amount' => $locked->total_amount,
                'status' => 'pending',
                'provider' => 'vnpay',
            ]);
            $txnRef = now('Asia/Ho_Chi_Minh')->format('ymdHis').Str::upper(Str::random(12));
            $session = $attempt->vnpaySession()->create([
                'txn_ref' => $txnRef,
                'amount_vnd' => (int) $amount,
                'vnp_amount' => (int) round($amount * 100),
                'payment_url' => '',
            ]);
            $paymentUrl = $this->paymentUrl($locked, $session, $ipAddress);
            $session->update(['payment_url' => $paymentUrl]);

            return $paymentUrl;
        });
    }

    public function restart(Booking $booking, VnpayPaymentSession $session, string $idempotencyKey, string $ipAddress): string
    {
        return DB::transaction(function () use ($booking, $session, $idempotencyKey, $ipAddress) {
            $lockedBooking = app(PaymentWorkflow::class)->lockBooking($booking->getKey());
            $lockedSession = VnpayPaymentSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $attempt = $lockedSession->attempt()->lockForUpdate()->firstOrFail();
            $payment = $attempt->payment()->lockForUpdate()->firstOrFail();

            abort_unless((int) $payment->booking_id === (int) $lockedBooking->getKey(), 404);
            $isPending = $lockedSession->status === 'pending';
            $isFailedCallback = $lockedSession->status === 'failed'
                && $lockedSession->response_code !== null
                && $attempt->status === 'pending'
                && $payment->status === 'pending';
            abort_unless(($isPending || $isFailedCallback) && $attempt->status === 'pending' && $payment->status === 'pending', 409, 'Phiên VNPay này không còn chờ xử lý.');
            abort_if($payment->attempts()->where('status', 'pending')->where('id', '!=', $attempt->getKey())->exists(), 409, 'Đơn hàng đang có một giao dịch khác chờ xử lý.');

            $lockedSession->update([
                'status' => 'cancelled',
                'gateway_response' => ['local_note' => 'Customer closed the previous sandbox session before starting a new one.'],
            ]);
            $attempt->update(['status' => 'failed']);
            $payment->update(['status' => 'failed']);

            return $this->start($lockedBooking, $idempotencyKey, $ipAddress);
        });
    }

    public function settleReturn(array $query): VnpayPaymentSession
    {
        foreach ($query as $value) {
            abort_unless(is_scalar($value) || $value === null, 400, 'Invalid VNPay response data.');
        }
        $providedHash = strtolower((string) ($query['vnp_SecureHash'] ?? ''));
        abort_unless($providedHash !== '', 403, 'Thiếu chữ ký phản hồi VNPay.');

        $signedFields = collect($query)
            ->except(['vnp_SecureHash', 'vnp_SecureHashType'])
            ->all();
        $expectedHash = hash_hmac('sha512', $this->canonical($signedFields), (string) config('payments.vnpay.hash_secret'));
        abort_unless(hash_equals($expectedHash, $providedHash), 403, 'Chữ ký phản hồi VNPay không hợp lệ.');

        return DB::transaction(function () use ($query) {
            $session = VnpayPaymentSession::query()
                ->where('txn_ref', (string) ($query['vnp_TxnRef'] ?? ''))
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(hash_equals((string) config('payments.vnpay.tmn_code'), (string) ($query['vnp_TmnCode'] ?? '')), 403);
            abort_unless((int) ($query['vnp_Amount'] ?? -1) === $session->vnp_amount, 409, 'Số tiền VNPay không khớp với đơn hàng.');

            $responseCode = (string) ($query['vnp_ResponseCode'] ?? '');
            $transactionStatus = (string) ($query['vnp_TransactionStatus'] ?? '');
            $outcome = $responseCode === '00' && $transactionStatus === '00' ? 'succeeded' : 'failed';
            $attempt = $session->attempt()->lockForUpdate()->firstOrFail();

            if ($session->status !== 'pending') {
                abort_unless($session->status === $outcome, 409, 'VNPay trả về kết quả giao dịch không khớp với kết quả đã lưu.');
                if ($attempt->status === 'pending') {
                    $this->settleAttempt($session, $attempt, $outcome, $query);
                }

                return $session->refresh();
            }

            $session->update([
                'status' => $outcome,
                'response_code' => $responseCode,
                'transaction_no' => filled($query['vnp_TransactionNo'] ?? null) && (string) $query['vnp_TransactionNo'] !== '0'
                    ? (string) $query['vnp_TransactionNo']
                    : null,
                'gateway_response' => $this->safeResponse($query),
            ]);
            $this->settleAttempt($session, $attempt, $outcome, $query);

            return $session->refresh();
        });
    }

    private function settleAttempt(VnpayPaymentSession $session, PaymentAttempt $attempt, string $outcome, array $query): void
    {
        $transactionNo = trim((string) ($query['vnp_TransactionNo'] ?? ''));
        $providerReference = $outcome === 'succeeded' && $transactionNo !== '' && $transactionNo !== '0'
            ? $transactionNo
            : $session->txn_ref;

        app(PaymentWorkflow::class)->settle($attempt, $outcome, $providerReference);
    }

    private function paymentUrl(Booking $booking, VnpayPaymentSession $session, string $ipAddress): string
    {
        $now = now('Asia/Ho_Chi_Minh');
        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => config('payments.vnpay.tmn_code'),
            'vnp_Amount' => (string) $session->vnp_amount,
            'vnp_BankCode' => 'NCB',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $ipAddress !== '' ? $ipAddress : '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan booking '.$booking->booking_code,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => filled(config('payments.vnpay.return_url'))
                ? config('payments.vnpay.return_url')
                : route('payments.vnpay.return'),
            'vnp_TxnRef' => $session->txn_ref,
            'vnp_ExpireDate' => $now->copy()->addMinutes(max(5, min(30, (int) config('payments.vnpay.expire_minutes', 15))))->format('YmdHis'),
        ];

        $params['vnp_SecureHash'] = hash_hmac(
            'sha512',
            $this->canonical($params),
            (string) config('payments.vnpay.hash_secret')
        );

        return rtrim(config('payments.vnpay.url'), '?').'?'.http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }

    private function canonical(array $fields): string
    {
        ksort($fields);
        $pairs = [];
        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $pairs[] = urlencode((string) $key).'='.urlencode((string) $value);
        }

        return implode('&', $pairs);
    }

    private function safeResponse(array $query): array
    {
        unset($query['vnp_SecureHash'], $query['vnp_SecureHashType']);

        return $query;
    }
}
