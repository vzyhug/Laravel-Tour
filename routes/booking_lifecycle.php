<?php

use App\Http\Controllers\BookingLifecycleController;
use Illuminate\Support\Facades\Route;

Route::middleware('customer.auth')->group(function () {
    Route::get('/account/booking-changes', [BookingLifecycleController::class, 'index'])->name('customer.booking-changes.index');
    Route::get('/account/bookings/{booking}/change', [BookingLifecycleController::class, 'edit'])->name('customer.booking-changes.edit');
    Route::post('/account/bookings/{booking}/change', [BookingLifecycleController::class, 'update'])->name('customer.booking-changes.update');
});
