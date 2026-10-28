<?php

use App\Http\Controllers\Operations\DepartureOperationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('operations')->name('operations.')->middleware('admin.auth')->group(function () {
    Route::get('/departures', [DepartureOperationsController::class, 'index'])->name('index');
    Route::get('/departures/{departure}', [DepartureOperationsController::class, 'show'])->name('show');
    Route::put('/departures/{departure}/passengers/{passenger}/attendance', [DepartureOperationsController::class, 'attendance'])->name('attendance');
    Route::patch('/departures/{departure}/status', [DepartureOperationsController::class, 'status'])->name('status');
    Route::put('/departures/{departure}/report', [DepartureOperationsController::class, 'report'])->name('report');
});
