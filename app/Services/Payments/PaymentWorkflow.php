<?php

namespace App\Services\Payments;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\TourDeparture;
use Illuminate\Support\Facades\DB;

class PaymentWorkflow
{
    public function pay(Booking $booking, string $method, string $key, string $outcome): PaymentAttempt
    {
        abort_unless($method !== 'bank_transfer', 409, 'Thanh toán ngân hàng phải đi qua cổng VNPay.');
        abort_unless(in_array($outcome, ['pending', 'succeeded', 'failed'], true), 422);

        return DB::transaction(function () use ($booking, $method, $key, $outcome) {
            $booking = $this->lockBooking($booking->getKey());
            $existing = PaymentAttempt::where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless($existing->payment->booking_id === $booking->getKey(), 409);

                return $existing;
            }
            abort_unless(in_array($booking->status, ['pending', 'confirmed'], true), 409, 'Đơn hàng đã đóng.');
            abort_if($booking->holdHasExpired() || $booking->departure->status === 'cancelled', 409, 'Đã hết hạn giữ chỗ hoặc chuyến đi đã hủy.');
            $payment = $booking->payment()->lockForUpdate()->first();
            abort_if($payment && in_array($payment->status, ['completed', 'refunded'], true), 409, 'Đơn hàng đã được thanh toán.');
            abort_if($payment && $payment->attempts()->where('status', 'pending')->exists(), 409, 'Đang có giao dịch chờ xử lý.');
            abort_if($booking->paymentIntents()->where('status', 'review')->exists(), 409, 'Đơn có giao dịch cần kiểm tra.');
            $payment ??= $booking->payment()->create(['amount' => $booking->total_amount, 'payment_method' => $method, 'status' => 'pending']);
            $payment->update(['payment_method' => $method, 'status' => 'pending']);
            $attempt = $payment->attempts()->create(['idempotency_key' => $key, 'amount' => $booking->total_amount, 'status' => 'pending', 'provider' => 'mock']);

            return $this->settle($attempt, $outcome);
        });
    }

    public function settle(PaymentAttempt $attempt, string $outcome, ?string $providerReference = null): PaymentAttempt
    {
        abort_unless(in_array($outcome, ['pending', 'succeeded', 'failed'], true), 422);

        return DB::transaction(function () use ($attempt, $outcome, $providerReference) {
            $bookingId = PaymentAttempt::findOrFail($attempt->id)->payment()->firstOrFail()->booking_id;
            $booking = $this->lockBooking($bookingId);
            $payment = $booking->payment()->lockForUpdate()->firstOrFail();
            $attempt = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status !== 'pending') {
                return $attempt;
            }
            if (in_array($attempt->provider, ['momo', 'vnpay'], true)) {
                abort_unless($providerReference !== null, 403, 'Kết quả cổng thanh toán phải được xác thực bởi nhà cung cấp.');
                $gateway = (object) [
                    'kind' => 'payment', 'idempotency_key' => 'payment:'.$attempt->idempotency_key,
                    'amount' => (float) $attempt->amount, 'status' => $outcome, 'reference' => $providerReference,
                ];
            } else {
                abort_unless($attempt->provider === 'mock', 409, 'Nhà cung cấp thanh toán không hợp lệ.');
                $gateway = app(MockPaymentGateway::class)->transact('payment', 'payment:'.$attempt->idempotency_key, (float) $attempt->amount, $outcome);
            }
            abort_unless(
                $gateway->kind === 'payment'
                && $gateway->idempotency_key === 'payment:'.$attempt->idempotency_key
                && round((float) $gateway->amount, 2) === round((float) $attempt->amount, 2)
                && round((float) $attempt->amount, 2) === round((float) $payment->amount, 2)
                && round((float) $payment->amount, 2) === round((float) $booking->total_amount, 2)
                && in_array($gateway->status, ['pending', 'succeeded', 'failed'], true),
                409,
                'Giao dịch mô phỏng không khớp đơn hàng. Vui lòng đối soát trước khi xử lý.'
            );
            // The explicit demo resolution updates only an unsettled local transaction.
            if ($gateway->status === 'pending' && $outcome !== 'pending') {
                DB::table('mock_gateway_transactions')->where('id', $gateway->id)->where('status', 'pending')->update(['status' => $outcome, 'updated_at' => now()]);
                $gateway = DB::table('mock_gateway_transactions')->find($gateway->id);
            }
            $attempt->update(['status' => $gateway->status, 'provider_reference' => $gateway->reference]);
            if ($gateway->status === 'pending') {
                return $attempt->refresh();
            }
            if ($gateway->status === 'failed') {
                $payment->update(['status' => 'failed']);

                return $attempt->refresh();
            }
            $payment->update(['status' => 'completed', 'transaction_code' => $gateway->reference, 'payment_date' => now()]);
            if (! in_array($booking->status, ['pending', 'confirmed'], true) || $booking->holdHasExpired() || $booking->departure->status === 'cancelled') {
                // Release the reservation before refund processing: a failed refund must
                // never keep an expired reservation alive or prevent future expiry.
                if (in_array($booking->status, ['pending', 'confirmed'], true)) {
                    $departure = $booking->departure;
                    $departure->available_seats = min($departure->max_seats, $departure->available_seats + $booking->total_adults + $booking->total_children);
                    if ($departure->status === 'full') {
                        $departure->status = 'open';
                    }
                    $departure->save();
                    $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'Thanh toán đến sau khi chỗ không còn hiệu lực.']);
                }
                app(RefundService::class)->request($booking, (float) $payment->amount, 'late-payment:'.$attempt->id, 'Thanh toán đến sau khi chỗ không còn hiệu lực.');
                $booking->update(['refund_percent' => 100, 'refund_amount' => $payment->amount]);

                return $attempt->refresh();
            }
            $booking->update(['status' => 'confirmed', 'hold_expires_at' => null]);
            $booking->paymentIntents()->where('status', 'pending')->update(['status' => 'cancelled']);
            Bill::firstOrCreate(['booking_id' => $booking->getKey()], ['bill_number' => 'INV-'.now()->format('Ymd').'-'.$booking->getKey(), 'final_amount' => $booking->total_amount]);

            return $attempt->refresh();
        });
    }

    public function lockBooking(int $bookingId): Booking
    {
        // Read a fresh snapshot rather than a route-bound model or cached relation.
        $departureId = Booking::whereKey($bookingId)->firstOrFail()->departure_id;
        $departure = TourDeparture::whereKey($departureId)->lockForUpdate()->firstOrFail();
        $booking = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
        abort_if((int) $booking->departure_id !== (int) $departureId, 409, 'Lịch khởi hành đã thay đổi. Vui lòng thử lại.');
        $booking->setRelation('departure', $departure);

        return $booking;
    }
}
