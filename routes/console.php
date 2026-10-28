<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping();
Schedule::command('customer:departure-reminders')->hourly()->withoutOverlapping();
require __DIR__.'/hotel_booking_schedule.php';

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
