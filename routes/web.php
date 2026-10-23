<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartureController as AdminDepartureController;
use App\Http\Controllers\Admin\EmployeeController as AdminEmployeeController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProvinceController as AdminProvinceController;
use App\Http\Controllers\Admin\TourController as AdminTourController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/services.php';

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::view('/support', 'support')->name('support');
Route::get('/tours', [TourController::class, 'index'])->name('tours.index');
Route::get('/tours/{tour}', [TourController::class, 'show'])->name('tours.show');
Route::get('/provinces/{province}/tours', [TourController::class, 'province'])->name('provinces.tours');

Route::group([], function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('customer.auth')->group(function () {
    Route::get('/booking/{departure}', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('/coupons/check', [BookingController::class, 'checkCoupon'])->name('coupons.check');
    Route::get('/payments/{booking}', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments/{booking}', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/account', [CustomerDashboardController::class, 'index'])->name('customer.dashboard');
    Route::get('/account/profile', [CustomerDashboardController::class, 'profile'])->name('customer.profile');
    Route::put('/account/profile', [CustomerDashboardController::class, 'updateProfile'])->name('customer.profile.update');
    Route::get('/account/bookings/{booking}', [CustomerDashboardController::class, 'show'])->name('customer.bookings.show');
    Route::get('/account/bookings/{booking}/invoice', [CustomerDashboardController::class, 'invoice'])->name('customer.bookings.invoice');
    Route::get('/account/bookings/{booking}/voucher', [CustomerDashboardController::class, 'voucher'])->name('customer.bookings.voucher');
    Route::post('/account/bookings/{booking}/review', [CustomerDashboardController::class, 'review'])->name('customer.bookings.review');
    Route::post('/account/bookings/{booking}/cancel', [CustomerDashboardController::class, 'cancel'])->name('customer.bookings.cancel');
    Route::get('/account/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{tour}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
});

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('admin.auth')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::middleware('admin.role:Admin,Sale')->group(function () {
            Route::resource('tours', AdminTourController::class)->except('show');
            Route::resource('departures', AdminDepartureController::class)->except('show');
            Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
            Route::patch('/customers/{customer}/status', [AdminCustomerController::class, 'update'])->name('customers.status');
            Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
            Route::get('/bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
            Route::patch('/bookings/{booking}/status', [AdminBookingController::class, 'updateStatus'])->name('bookings.status');
        });

        Route::middleware('admin.role:Admin')->group(function () {
            Route::resource('employees', AdminEmployeeController::class)->except(['show', 'destroy']);
            Route::get('/provinces', [AdminProvinceController::class, 'index'])->name('provinces.index');
            Route::post('/provinces', [AdminProvinceController::class, 'store'])->name('provinces.store');
            Route::resource('coupons', AdminCouponController::class)->except(['show', 'destroy']);
        });
    });
});
