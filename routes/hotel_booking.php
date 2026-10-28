<?php

use App\Http\Controllers\Admin\HotelBookingController as AdminHotelBookingController;
use App\Http\Controllers\HotelBookingController;
use Illuminate\Support\Facades\Route;

Route::get('/hotels/{hotel}/rooms', [HotelBookingController::class, 'search'])->name('hotel-rooms.search');
Route::middleware('customer.auth')->group(function () {
    Route::get('/account/hotel-bookings', [HotelBookingController::class, 'index'])->name('hotel-bookings.index');
    Route::post('/hotel-bookings', [HotelBookingController::class, 'store'])->name('hotel-bookings.store');
    Route::get('/account/hotel-bookings/{hotelBooking}', [HotelBookingController::class, 'show'])->name('hotel-bookings.show');
    Route::post('/account/hotel-bookings/{hotelBooking}/simulate-payment', [HotelBookingController::class, 'pay'])->name('hotel-bookings.pay');
    Route::post('/account/hotel-bookings/{hotelBooking}/cancel', [HotelBookingController::class, 'cancel'])->name('hotel-bookings.cancel');
});
Route::prefix('admin/hotel-booking')->name('admin.hotel-booking.')->middleware(['admin.auth', 'admin.role:Admin,Staff'])->group(function () {
    Route::get('/', [AdminHotelBookingController::class, 'index'])->name('index');
    Route::post('/room-types', [AdminHotelBookingController::class, 'storeRoomType'])->name('room-types.store');
    Route::get('/room-types/{roomType}/inventory', [AdminHotelBookingController::class, 'inventory'])->name('inventory');
    Route::put('/room-types/{roomType}/inventory', [AdminHotelBookingController::class, 'saveInventory'])->name('inventory.save');
    Route::post('/bookings/{hotelBooking}/complete', [AdminHotelBookingController::class, 'complete'])->name('complete');
});
