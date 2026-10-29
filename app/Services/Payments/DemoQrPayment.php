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
            $locked = app(PaymentWorkflow::class)->lockBooking($booking->getKey());
            abort_if($locked->holdHasExpired() || $locked->departure->status === 'cancelled', 409, 'Đã hết hạn giữ chỗ hoặc chuyến đi đã hủy.');
            $this->assertPayable($locked);
            $payment = $locked->payment()->lockForUpdate()->first();
            abort_if($payment && $payment->status !== 'failed', 409, 'Đơn đã có giao dịch thanh toán.');
            abort_if($payment && $payment->attempts()->where('status', 'pending')->exists(), 409, 'Đơn đang có giao dịch chờ xử lý.');
            abort_if($locked->paymentIntents()->where('status', 'review')->exists(), 409, 'Đơn có giao dịch cần kiểm tra.');
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
            $booking = app(PaymentWorkflow::class)->lockBooking($intent->booking_id);
            $locked = PaymentIntent::lockForUpdate()->findOrFail($intent->id);
            $this->assertPayable($booking);
            abort_if($booking->status === 'pending' && $booking->holdHasExpired(), 409, 'Thời hạn giữ chỗ đã hết.');
            abort_if($booking->departure()->first()?->status === 'cancelled', 409, 'Chuyến đi đã hủy.');
            abort_unless($locked->provider === 'vietqr_demo', 409);
            if ($locked->status === 'paid') {
                return;
            }
            $payment = $booking->payment()->lockForUpdate()->first();
            abort_if($payment && $payment->status !== 'failed', 409, 'Đơn đã có giao dịch thanh toán khác.');
            abort_if($payment && $payment->attempts()->where('status', 'pending')->exists(), 409, 'Đơn đang có giao dịch chờ xử lý.');
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
            $paymentData = ['amount' => $locked->amount, 'payment_method' => 'bank_transfer', 'transaction_code' => $transaction,
                'status' => 'completed', 'payment_date' => now()];
            if ($payment) {
                $payment->update($paymentData);
            } else {
                Payment::create(['booking_id' => $booking->getKey(), ...$paymentData]);
            }
            $booking->update(['status' => 'confirmed', 'hold_expires_at' => null]);
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
        return app(PaymentStatus::class)->state($booking, $intent);
    }
}
