<?php

use App\Http\Controllers\CustomerPasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/forgot-password', [CustomerPasswordController::class, 'request'])->name('customer.password.request');
Route::post('/forgot-password', [CustomerPasswordController::class, 'email'])->middleware('throttle:5,1')->name('customer.password.email');
Route::get('/reset-password/{token}', [CustomerPasswordController::class, 'resetForm'])->name('customer.password.reset');
Route::post('/reset-password', [CustomerPasswordController::class, 'reset'])->middleware('throttle:6,1')->name('customer.password.update');
Route::middleware('customer.auth')->group(function () {
    Route::get('/account/password', [CustomerPasswordController::class, 'changeForm'])->name('customer.password.change');
    Route::put('/account/password', [CustomerPasswordController::class, 'change'])->middleware('throttle:6,1')->name('customer.password.change.update');
});
