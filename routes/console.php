<?php

use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\PlatformOrder;
use App\Services\PlatformOrders;
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

Artisan::command('services:expire', function () {
    $count = 0;
    PlatformOrder::where('reservation_status', 'held')->where('expires_at', '<=', now())
        ->eachById(function ($order) use (&$count) {
            $count += (int) app(PlatformOrders::class)->expire($order);
        });
    $this->info("Đã giải phóng {$count} đơn dịch vụ hết hạn.");
})->purpose('Giải phóng phòng, ghế và xe giữ quá hạn');
Schedule::command('services:expire')->everyMinute()->withoutOverlapping();
