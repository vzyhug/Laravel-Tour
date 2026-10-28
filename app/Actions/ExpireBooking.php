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
            $departureId = Booking::query()->findOrFail($booking->getKey())->departure_id;
            $departure = TourDeparture::query()->lockForUpdate()->findOrFail($departureId);
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            if ((int) $booking->departure_id !== (int) $departureId) {
                return false;
            }
            if ($booking->status !== 'pending' || ! $booking->holdHasExpired() || $booking->payment()->where('status', 'completed')->exists()) {
                return false;
            }
            $departure->available_seats = min($departure->max_seats, $departure->available_seats + $booking->total_adults + $booking->total_children);
            if ($departure->status === 'full') {
                $departure->status = 'open';
            }
            $departure->save();
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'Hết thời hạn giữ chỗ chưa thanh toán.']);

            return true;
        }, 3);
    }
}
