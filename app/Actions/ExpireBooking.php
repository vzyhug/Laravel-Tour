<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\TourDeparture;
use Illuminate\Support\Facades\DB;

class ExpireBooking
{
    public function handle(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->getKey());
            if ($locked->status !== 'pending' || ! $locked->payment_expires_at?->isPast() || $locked->payment()->exists()) {
                return false;
            }
            $departure = TourDeparture::lockForUpdate()->findOrFail($locked->departure_id);
            $departure->available_seats = min($departure->max_seats, $departure->available_seats + $locked->total_adults + $locked->total_children);
            if ($departure->status === 'full') {
                $departure->status = 'open';
            }
            $departure->save();
            $locked->update(['status' => 'cancelled', 'expired_at' => now(), 'cancelled_at' => now(), 'cancel_reason' => 'Hết thời hạn thanh toán; chỗ giữ đã được giải phóng.']);
            $locked->recordEvent('hold_expired', ['released_seats' => $locked->total_adults + $locked->total_children]);

            return true;
        });
    }

    public function forDeparture(int $departureId): void
    {
        Booking::where('departure_id', $departureId)->where('status', 'pending')
            ->where('payment_expires_at', '<', now())->eachById(fn (Booking $booking) => $this->handle($booking), 100, 'booking_id');
    }
}
