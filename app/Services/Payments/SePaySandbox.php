<?php

namespace App\Services\Payments;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentIntent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SePaySandbox
{
    public const PROVIDER = 'sepay_sandbox';

    public const CHECKOUT_URL = 'https://pay-sandbox.sepay.vn/v1/checkout/init';

    public const API_URL = 'https://pgapi-sandbox.sepay.vn/v1/order/detail/';

    public static function enabled(): bool
    {
        return (bool) config('payments.sepay_sandbox.enabled') && ! app()->isProduction();
    }

    public static function ready(): bool
    {
        if (! self::enabled()) {
            return false;
        }
        foreach (['merchant_id', 'secret_key'] as $key) {
            if (trim((string) config('payments.sepay_sandbox.'.$key)) === '') {
                return false;
            }
        }

        return true;
    }

    public function start(Booking $booking): PaymentIntent
    {
        abort_unless(self::ready(), 503, 'SePay Sandbox chưa được cấu hình đầy đủ.');

        return DB::transaction(function () use ($booking) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->getKey());
            abort_unless(in_array($locked->status, ['pending', 'confirmed'], true), 409);
            abort_if($locked->payment()->exists(), 409, 'Đơn đã có giao dịch thanh toán.');
            abort_if($locked->paymentIntents()->where('status', 'review')->exists(), 409, 'Đơn có giao dịch cần kiểm tra.');
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
        // Preserve the insertion order: SePay signs the submitted field order, as in its PHP SDK.
        // https://github.com/sepayvn/sepay-pg-php/blob/main/src/Auth/SignatureGenerator.php
        $fields = [
            'merchant' => (string) config('payments.sepay_sandbox.merchant_id'), 'currency' => 'VND',
            'order_amount' => explode('.', $intent->amount)[0], 'operation' => 'PURCHASE',
            'order_description' => 'VietGo sandbox '.$intent->reference, 'payment_method' => 'BANK_TRANSFER',
            'order_invoice_number' => $intent->reference,
        ];
        $signed = [];
        foreach ($fields as $name => $value) {
            $signed[] = $name.'='.$value;
        }
        $fields['signature'] = base64_encode(hash_hmac('sha256', implode(',', $signed), config('payments.sepay_sandbox.secret_key'), true));

        return $fields;
    }

    public function synchronize(PaymentIntent $intent): bool
    {
        if (! self::ready()) {
            return false;
        }
        if ($intent->provider !== self::PROVIDER || in_array($intent->status, ['paid', 'review'], true)) {
            return true;
        }
        // Share a short result across tabs; never hold a booking lock while waiting for SePay.
        $key = 'sepay-sandbox-lookup:'.$intent->id;
        $cached = Cache::get($key);
        if (is_bool($cached)) {
            return $cached;
        }
        $lock = Cache::lock($key.':lock', 10);
        if (! $lock->get()) {
            return true;
        }
        try {
            $available = $this->lookup($intent);
            Cache::put($key, $available, 5);

            return $available;
        } finally {
            $lock->release();
        }
    }

    private function lookup(PaymentIntent $intent): bool
    {
        try {
            $response = Http::withBasicAuth(config('payments.sepay_sandbox.merchant_id'), config('payments.sepay_sandbox.secret_key'))
                ->withOptions(['verify' => config('payments.sepay_sandbox.ca_bundle') ?: true])->acceptJson()->connectTimeout(3)->timeout(6)->withoutRedirecting()->get(self::API_URL.rawurlencode($intent->reference));
        } catch (ConnectionException) {
            return false;
        }
        // SePay may not have created the provider order yet. This is not a successful payment.
        if ($response->status() === 404) {
            return true;
        }
        if (! $response->successful()) {
            return false;
        }
        $data = $response->json('data');
        if (! is_array($data) || Validator::make($data, [
            'order_id' => ['required', 'string', 'max:70', 'regex:/^[a-zA-Z0-9_-]+$/D'],
            'order_invoice_number' => ['required', 'string', 'max:25'], 'order_status' => ['required', 'string', 'max:30'],
            'order_currency' => ['required', 'string', 'max:3'], 'order_amount' => ['required', 'regex:/^[0-9]{1,13}(?:\.[0-9]{1,2})?$/D'],
        ])->fails() || $data['order_invoice_number'] !== $intent->reference) {
            return false;
        }
        if ($data['order_status'] === 'CAPTURED') {
            $this->recordVerifiedOrder($intent, $data);
        } elseif ($data['order_status'] === 'CANCELLED') {
            DB::transaction(function () use ($intent) {
                Booking::lockForUpdate()->findOrFail($intent->booking_id);
                $locked = PaymentIntent::lockForUpdate()->findOrFail($intent->id);
                if ($locked->status === 'pending') {
                    $locked->update(['status' => 'cancelled']);
                }
            });
        } elseif ($data['order_status'] !== 'AUTHENTICATION_NOT_NEEDED') {
            return false;
        }

        return true;
    }

    private function recordVerifiedOrder(PaymentIntent $intent, array $order): void
    {
        DB::transaction(function () use ($intent, $order) {
            $booking = Booking::lockForUpdate()->findOrFail($intent->booking_id);
            $locked = PaymentIntent::lockForUpdate()->findOrFail($intent->id);
            // Bank-transfer sandbox orders can have an empty transactions array. Record the actual
            // SePay order ID, not an invented bank transaction. CAPTURED is verified by the private API.
            $reference = 'ORDER-'.$order['order_id'];
            $identity = ['provider' => self::PROVIDER, 'notification_type' => 'ORDER_VERIFIED', 'transaction_id' => $reference];
            $event = DB::table('payment_gateway_events')->where($identity)->first();
            if ($event) {
                abort_unless($event->payment_intent_id === $locked->id, 409);

                return;
            }
            if ($locked->status === 'paid') {
                return;
            }
            $reason = null;
            if ($order['order_currency'] !== 'VND' || ! $this->sameAmount((string) $order['order_amount'], $locked->amount)) {
                $reason = 'currency_or_amount_mismatch';
            } elseif ($locked->status !== 'pending' || $locked->expires_at->lessThanOrEqualTo(now())
                || ! in_array($booking->status, ['pending', 'confirmed'], true) || $booking->payment()->exists() || $booking->total_amount !== $locked->amount
                || $booking->paymentIntents()->where('status', 'review')->exists()) {
                $reason = 'booking_or_attempt_closed';
            }
            DB::table('payment_gateway_events')->insert($identity + [
                'payment_intent_id' => $locked->id, 'outcome' => $reason ? 'review' : 'paid', 'reason' => $reason,
                'details' => json_encode(['source' => 'sepay_order_api', 'order_id' => $order['order_id'], 'order_status' => $order['order_status'],
                    'order_amount' => $order['order_amount'], 'order_currency' => $order['order_currency']]), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($reason) {
                $locked->update(['status' => 'review']);
                $booking->paymentIntents()->where('status', 'pending')->update(['status' => 'cancelled']);

                return;
            }
            $code = 'SEPAY-SBX-'.$reference;
            Payment::create(['booking_id' => $booking->getKey(), 'amount' => $locked->amount, 'payment_method' => 'bank_transfer', 'transaction_code' => $code, 'status' => 'completed']);
            Bill::firstOrCreate(['booking_id' => $booking->getKey()], ['bill_number' => 'INV-'.now()->format('Ymd').'-'.$booking->getKey(), 'final_amount' => $locked->amount]);
            $booking->update(['status' => 'confirmed']);
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
