<?php

use App\Actions\ExpireBooking;
use App\Models\Booking;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire', function () {
    $count = 0;
    Booking::where('status', 'pending')->where('payment_expires_at', '<', now())
        ->eachById(function (Booking $booking) use (&$count) {
            $count += (int) app(ExpireBooking::class)->handle($booking);
        }, 100, 'booking_id');
    $this->info("Đã giải phóng {$count} đơn hết hạn.");
})->purpose('Giải phóng chỗ giữ của đơn chưa thanh toán hết hạn');

Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping();
