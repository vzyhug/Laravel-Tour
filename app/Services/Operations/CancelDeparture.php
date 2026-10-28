<?php

namespace App\Services\Operations;

use App\Events\DepartureCancelled;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\TourDeparture;
use App\Services\Payments\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelDeparture
{
    public function __construct(private RefundService $refunds) {}

    public function handle(TourDeparture $departure, int $employeeId, string $reason): TourDeparture
    {
        return DB::transaction(function () use ($departure, $employeeId, $reason) {
            $actor = Employee::with('role')->findOrFail($employeeId);
            abort_unless($actor->is_active && in_array($actor->role->role_name, ['Admin', 'Sale'], true), 403);
            // Every booking flow locks departure before booking to serialize capacity changes.
            $departure = TourDeparture::lockForUpdate()->findOrFail($departure->getKey());
            if ($departure->status === 'cancelled') {
                return $departure;
            }
            if (in_array($departure->operation_status, ['in_progress', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => 'Chuyến đã bắt đầu hoặc hoàn thành, không thể hủy.']);
            }
            // Persist inside the same transaction so booking observers can identify organizer cancellation.
            $departure->forceFill([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $employeeId,
                'cancellation_reason' => $reason,
            ])->save();
            $bookings = Booking::where('departure_id', $departure->getKey())
                ->whereIn('status', ['pending', 'confirmed'])->orderBy('booking_id')->lockForUpdate()->get();
            foreach ($bookings as $booking) {
                $payment = $booking->payment()->lockForUpdate()->first();
                $refundAmount = null;
                if ($payment && $payment->status === 'completed') {
                    // Organizer cancellation refunds the remaining collected amount in full.
                    $refunded = DB::table('payment_refunds')->where('payment_id', $payment->getKey())
                        ->whereIn('status', ['requested', 'processing', 'succeeded'])->sum('amount');
                    $refundAmount = max(0, round((float) $payment->amount - (float) $refunded, 2));
                    if ($refundAmount > 0) {
                        $this->refunds->request($booking, $refundAmount, 'departure-cancel:'.$booking->getKey(), $reason);
                    }
                }
                $booking->forceFill([
                    'status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason,
                    'refund_percent' => $refundAmount !== null ? 100 : null,
                    'refund_amount' => $refundAmount !== null ? $payment->amount : null,
                ])->save();
            }
            $departure->forceFill([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $employeeId,
                'cancellation_reason' => $reason,
                'available_seats' => min($departure->max_seats, $departure->available_seats + $bookings->sum(fn ($b) => $b->total_adults + $b->total_children)),
            ])->save();
            $ids = $bookings->modelKeys();
            DB::afterCommit(fn () => DepartureCancelled::dispatch($departure->getKey(), $ids, $reason));

            return $departure;
        }, 3);
    }
}
