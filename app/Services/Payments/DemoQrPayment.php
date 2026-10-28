<?php

namespace App\Services\Payments;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DemoQrPayment
{
    // Intentionally not a real NAPAS bank or a real receiving account.
    public const BANK_BIN = '000000';

    public const ACCOUNT = 'VIETGODEMO';

    public static function enabled(): bool
    {
        return (bool) config('payments.demo_enabled') && ! app()->isProduction();
    }

    public function start(Booking $booking): PaymentIntent
    {
        abort_unless(self::enabled(), 404);

        return DB::transaction(function () use ($booking) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->getKey());
            $this->assertPayable($locked);
            abort_if($locked->payment()->exists(), 409, 'Đơn đã có giao dịch thanh toán.');
            if (! preg_match('/^([1-9][0-9]{0,12})\.00$/D', $locked->total_amount, $match)) {
                throw ValidationException::withMessages(['payment_method' => 'Thanh toán QR cần số tiền nguyên VND lớn hơn 0.']);
            }
            $locked->paymentIntents()->where('status', 'pending')->where('expires_at', '<=', now())->update(['status' => 'expired']);
            $pending = $locked->paymentIntents()->where('status', 'pending')->where('expires_at', '>', now())->first();
            if ($pending && $pending->provider === 'vietqr_demo' && $pending->amount === $locked->total_amount) {
                return $pending;
            }
            $locked->paymentIntents()->where('status', 'pending')->update(['status' => 'cancelled']);
            $reference = 'DEMO'.Str::upper(Str::random(16));

            return $locked->paymentIntents()->create([
                'reference' => $reference, 'amount' => $locked->total_amount, 'currency' => 'VND', 'provider' => 'vietqr_demo',
                'qr_payload' => app(VietQrPayload::class)->generate(self::BANK_BIN, self::ACCOUNT, $match[1], $reference),
                'expires_at' => now()->addMinutes(max(1, min(30, (int) config('payments.qr_lifetime_minutes')))),
            ]);
        });
    }

    public function simulate(PaymentIntent $intent, string $outcome): void
    {
        abort_unless(self::enabled(), 404);
        abort_unless(in_array($outcome, ['success', 'failed', 'expired'], true), 422);
        // Every writer locks the booking first, including cancellation and legacy payments.
        DB::transaction(function () use ($intent, $outcome) {
            $booking = Booking::lockForUpdate()->findOrFail($intent->booking_id);
            $locked = PaymentIntent::lockForUpdate()->findOrFail($intent->id);
            $this->assertPayable($booking);
            abort_unless($locked->provider === 'vietqr_demo', 409);
            if ($locked->status === 'paid') {
                return;
            }
            abort_if($booking->payment()->exists(), 409, 'Đơn đã có giao dịch thanh toán khác.');
            abort_unless($locked->status === 'pending', 409, 'Mã QR đã đóng. Hãy tạo mã QR mới.');
            if ($locked->expires_at->lessThanOrEqualTo(now()) || $locked->amount !== $booking->total_amount) {
                $locked->update(['status' => $locked->expires_at->lessThanOrEqualTo(now()) ? 'expired' : 'cancelled']);

                return;
            }
            if ($outcome !== 'success') {
                $locked->update(['status' => $outcome, ...($outcome === 'expired' ? ['expires_at' => now()] : [])]);

                return;
            }
            $transaction = 'DEMO-'.$locked->id;
            Payment::create(['booking_id' => $booking->getKey(), 'amount' => $locked->amount, 'payment_method' => 'bank_transfer',
                'transaction_code' => $transaction, 'status' => 'completed']);
            $booking->update(['status' => 'confirmed']);
            Bill::firstOrCreate(['booking_id' => $booking->getKey()], ['bill_number' => 'INV-'.now()->format('Ymd').'-'.$booking->getKey(), 'final_amount' => $locked->amount]);
            $locked->update(['status' => 'paid', 'paid_at' => now(), 'provider_transaction_id' => $transaction]);
            $booking->paymentIntents()->whereKeyNot($locked->id)->where('status', 'pending')->update(['status' => 'cancelled']);
        });
    }

    private function assertPayable(Booking $booking): void
    {
        abort_unless(in_array($booking->status, ['pending', 'confirmed'], true), 409, 'Đơn đã đóng, không thể thanh toán.');
    }

    public function state(Booking $booking, PaymentIntent $intent): array
    {
        $booking->loadMissing('payment', 'bill');
        $status = $intent->status;
        if ($booking->status === 'cancelled') {
            $status = 'cancelled';
        } elseif ($booking->payment?->status === 'completed') {
            $status = $intent->status === 'paid' ? 'paid' : 'superseded';
        } elseif (! in_array($booking->status, ['pending', 'confirmed'], true) || $intent->amount !== $booking->total_amount) {
            $status = 'cancelled';
        } elseif ($status === 'pending' && $intent->expires_at->lessThanOrEqualTo(now())) {
            $status = 'expired';
        }

        return ['status' => $status, 'reference' => $intent->reference, 'amount' => $intent->amount,
            'seconds_remaining' => $status === 'pending' ? max(0, $intent->expires_at->timestamp - now()->timestamp) : 0,
            'can_retry' => in_array($booking->status, ['pending', 'confirmed'], true) && ! $booking->payment && in_array($status, ['expired', 'failed', 'cancelled'], true),
            'booking_url' => route('customer.bookings.show', $booking),
            'invoice_url' => $booking->bill && in_array($status, ['paid', 'superseded'], true) ? route('customer.bookings.invoice', $booking) : null];
    }
}
