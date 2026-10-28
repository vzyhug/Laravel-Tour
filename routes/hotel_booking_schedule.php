<?php

// Require from routes/console.php. No inventory or booking data is modified at registration.
use App\Services\HotelBookingService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => app(HotelBookingService::class)->expire())->name('hotel-bookings-expire')->everyMinute()->withoutOverlapping();
