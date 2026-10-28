<?php

namespace App\Services\Payments;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SePaySandbox
{
    public const PROVIDER = 'sepay_sandbox';

    public const CHECKOUT_URL = 'https://pay-sandbox.sepay.vn/v1/checkout/init';

    public static function enabled(): bool
    {
        return (bool) config('payments.sepay_sandbox.enabled') && ! app()->isProduction();
    }

    public static function ready(): bool
    {
        if (! self::enabled()) {
            return false;
        }
        foreach (['merchant_id', 'secret_key', 'ipn_secret'] as $key) {
            if (trim((string) config('payments.sepay_sandbox.'.$key)) === '') {
                return false;
            }
        }
        $url = (string) config('payments.sepay_sandbox.public_url');

        return filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_QUERY) && ! parse_url($url, PHP_URL_FRAGMENT);
    }

    public function start(Booking $booking): PaymentIntent
    {
        abort_unless(self::ready(), 503, 'SePay Sandbox chưa được cấu hình đầy đủ.');

        return DB::transaction(function () use ($booking) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->getKey());
            abort_unless(in_array($locked->status, ['pending', 'confirmed'], true), 409);
            $payment = $locked->payment()->lockForUpdate()->first();
            abort_if($payment && $payment->status !== 'failed', 409, 'Đơn đã có giao dịch thanh toán.');
            abort_if($payment && $payment->attempts()->where('status', 'pending')->exists(), 409, 'Đơn đang có giao dịch chờ xử lý.');
            abort_if($locked->paymentIntents()->where('status', 'review')->exists(), 409, 'Đơn có giao dịch cần kiểm tra.');
            abort_if($locked->status === 'pending' && $locked->holdHasExpired(), 409, 'Thời hạn giữ chỗ đã hết.');
            abort_if($locked->departure()->first()?->status === 'cancelled', 409, 'Chuyến đi đã hủy.');
            if (! preg_match('/^([1-9][0-9]{0,12})\.00$/D', $locked->total_amount)) {
                throw ValidationException::withMessages(['payment_method' => 'SePay cần số tiền nguyên VND lớn hơn 0.']);
            }
            $locked->paymentIntents()->where('status', 'pending')->where('expires_at', '<=', now())->update(['status' => 'expired']);
            $intent = $locked->paymentIntents()->where('provider', self::PROVIDER)->where('status', 'pending')->first();
            if ($intent && $intent->amount === $locked->total_amount) {
                return $intent;
            }
            $locked->paymentIntents()->where('status', 'pending')->update(['status' => 'cancelled']);

            return $locked->paymentIntents()->create([
                'reference' => 'SP'.Str::upper(Str::random(20)), 'provider' => self::PROVIDER,
                'amount' => $locked->total_amount, 'currency' => 'VND', 'qr_payload' => '',
                'expires_at' => now()->addMinutes(max(1, min(30, (int) config('payments.qr_lifetime_minutes')))),
            ]);
        });
    }

    public function fields(PaymentIntent $intent): array
    {
        abort_unless(self::ready(), 503);
        $return = rtrim(config('payments.sepay_sandbox.public_url'), '/').'/sepay-sandbox/return';
        // Preserve the insertion order: SePay signs the submitted field order, as in its PHP SDK.
        // https://github.com/sepayvn/sepay-pg-php/blob/main/src/Auth/SignatureGenerator.php
        $fields = [
            'merchant' => (string) config('payments.sepay_sandbox.merchant_id'), 'currency' => 'VND',
            'order_amount' => explode('.', $intent->amount)[0], 'operation' => 'PURCHASE',
            'order_description' => 'VietGo sandbox '.$intent->reference, 'payment_method' => 'BANK_TRANSFER',
            'order_invoice_number' => $intent->reference, 'success_url' => $return, 'error_url' => $return, 'cancel_url' => $return,
        ];
        $signed = [];
        foreach ($fields as $name => $value) {
            $signed[] = $name.'='.$value;
        }
        $fields['signature'] = base64_encode(hash_hmac('sha256', implode(',', $signed), config('payments.sepay_sandbox.secret_key'), true));

        return $fields;
    }

    public function receive(array $data): void
    {
        $intent = PaymentIntent::where('provider', self::PROVIDER)->where('reference', $data['order']['order_invoice_number'])->firstOrFail();
        DB::transaction(function () use ($intent, $data) {
            $booking = Booking::lockForUpdate()->findOrFail($intent->booking_id);
            $locked = PaymentIntent::lockForUpdate()->findOrFail($intent->id);
            $order = $data['order'];
            $transaction = $data['transaction'];
            $payment = $booking->payment()->lockForUpdate()->first();
            $identity = ['provider' => self::PROVIDER, 'notification_type' => $data['notification_type'], 'transaction_id' => $transaction['transaction_id']];
            $event = DB::table('payment_gateway_events')->where($identity)->first();
            if ($event) {
                abort_unless($event->payment_intent_id === $locked->id, 409);

                return;
            }
            $reason = null;
            if ($data['notification_type'] === 'TRANSACTION_VOID') {
                $reason = 'provider_void_requires_review';
            } elseif ($order['order_status'] !== 'CAPTURED' || $transaction['transaction_status'] !== 'APPROVED'
                || $transaction['transaction_type'] !== 'PAYMENT' || $transaction['payment_method'] !== 'BANK_TRANSFER') {
                $reason = 'unexpected_provider_status';
            } elseif ($order['order_currency'] !== 'VND' || $transaction['transaction_currency'] !== 'VND'
                || ! $this->sameAmount($order['order_amount'], $locked->amount) || ! $this->sameAmount($transaction['transaction_amount'], $locked->amount)) {
                $reason = 'currency_or_amount_mismatch';
            } elseif ($locked->status !== 'pending' || $locked->expires_at->lessThanOrEqualTo(now())
                || ! in_array($booking->status, ['pending', 'confirmed'], true)
                || ($booking->status === 'pending' && $booking->holdHasExpired())
                || $booking->departure()->first()?->status === 'cancelled'
                || ($payment && $payment->status !== 'failed')
                || ($payment && $payment->attempts()->where('status', 'pending')->exists())
                || $booking->total_amount !== $locked->amount) {
                $reason = 'booking_or_attempt_closed';
            } elseif (PaymentIntent::where('provider_transaction_id', 'SEPAY-SBX-'.$transaction['transaction_id'])->exists()) {
                $reason = 'transaction_already_used';
            }
            // Keep only reconciliation fields; never persist API keys, bank/card/customer details, or the raw IPN.
            DB::table('payment_gateway_events')->insert($identity + [
                'payment_intent_id' => $locked->id, 'outcome' => $reason ? 'review' : 'paid', 'reason' => $reason,
                'details' => json_encode(['order_id' => $order['order_id'], 'order_amount' => $order['order_amount'],
                    'transaction_amount' => $transaction['transaction_amount'], 'order_currency' => $order['order_currency'],
                    'transaction_currency' => $transaction['transaction_currency']]), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($reason) {
                $locked->update(['status' => 'review']);
                $booking->paymentIntents()->where('status', 'pending')->update(['status' => 'cancelled']);

                return;
            }
            $code = 'SEPAY-SBX-'.$transaction['transaction_id'];
            $paymentData = ['amount' => $locked->amount, 'payment_method' => 'bank_transfer', 'transaction_code' => $code, 'status' => 'completed', 'payment_date' => now()];
            if ($payment) {
                $payment->update($paymentData);
            } else {
                Payment::create(['booking_id' => $booking->getKey(), ...$paymentData]);
            }
            Bill::firstOrCreate(['booking_id' => $booking->getKey()], ['bill_number' => 'INV-'.now()->format('Ymd').'-'.$booking->getKey(), 'final_amount' => $locked->amount]);
            $booking->update(['status' => 'confirmed', 'hold_expires_at' => null]);
            $locked->update(['status' => 'paid', 'provider_transaction_id' => $code, 'paid_at' => now()]);
            $booking->paymentIntents()->whereKeyNot($locked->id)->where('status', 'pending')->update(['status' => 'cancelled']);
        });
    }

    private function sameAmount(string $received, string $expected): bool
    {
        if (! preg_match('/^([0-9]{1,13})(?:\.(0{1,2}))?$/D', $received, $match)) {
            return false;
        }

        return ((string) (int) $match[1]).'.00' === $expected;
    }
}
