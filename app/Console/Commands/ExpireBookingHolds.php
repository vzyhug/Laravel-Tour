<?php

namespace App\Console\Commands;

use App\Actions\ExpireBooking;
use App\Models\Booking;
use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Hủy đơn chưa thanh toán đã hết hạn giữ chỗ và trả ghế';

    public function handle(ExpireBooking $expire): int
    {
        $count = 0;
        Booking::query()->where('status', 'pending')->whereNotNull('hold_expires_at')->where('hold_expires_at', '<=', now())
            ->chunkById(100, function ($bookings) use ($expire, &$count) {
                foreach ($bookings as $booking) {
                    $count += (int) $expire->handle($booking);
                }
            }, 'booking_id');
        $this->info("Đã hết hạn {$count} đơn giữ chỗ.");

        return self::SUCCESS;
    }
}
