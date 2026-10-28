<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function request(Booking $booking, float $amount, string $key, string $reason): PaymentRefund
    {
        return DB::transaction(function () use ($booking, $amount, $key, $reason) {
            $booking = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $payment = $booking->payment()->lockForUpdate()->firstOrFail();
            $existing = PaymentRefund::where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless($existing->payment_id === $payment->getKey() && (float) $existing->amount === $amount, 409);

                return $existing;
            }
            $reserved = (float) $payment->refunds()->whereIn('status', ['requested', 'processing', 'succeeded'])->sum('amount');
            if (! in_array($payment->status, ['completed', 'refunded'], true) || $amount <= 0 || round($reserved + $amount, 2) > (float) $payment->amount) {
                throw ValidationException::withMessages(['refund' => 'Số tiền hoàn không hợp lệ hoặc vượt số tiền đã thu.']);
            }
            $refund = $payment->refunds()->create(['idempotency_key' => $key, 'amount' => $amount, 'reason' => $reason, 'status' => 'requested']);

            return $this->process($refund);
        });
    }

    public function process(PaymentRefund $refund): PaymentRefund
    {
        return DB::transaction(function () use ($refund) {
            // Always lock the parent before its refund, matching request().
            $payment = $refund->payment()->lockForUpdate()->firstOrFail();
            $refund = PaymentRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === 'succeeded') {
                return $refund;
            }
            $other = (float) $payment->refunds()->whereKeyNot($refund->id)->whereIn('status', ['requested', 'processing', 'succeeded'])->sum('amount');
            abort_if(round($other + (float) $refund->amount, 2) > (float) $payment->amount, 409, 'Vượt số tiền có thể hoàn.');
            $refund->update(['status' => 'processing', 'attempt_count' => $refund->attempt_count + 1]);
            $outcome = config('payments.mock_refund_outcome', 'succeeded');
            abort_unless(in_array($outcome, ['succeeded', 'failed'], true), 500);
            $gateway = app(MockPaymentGateway::class)->transact('refund', 'refund:'.$refund->idempotency_key.':'.$refund->attempt_count, (float) $refund->amount, $outcome);
            $refund->update(['status' => $gateway->status, 'provider_reference' => $gateway->reference]);
            $total = (float) $payment->refunds()->where('status', 'succeeded')->sum('amount');
            if ($total >= (float) $payment->amount) {
                $payment->update(['status' => 'refunded']);
            }

            return $refund->refresh();
        });
    }
}
