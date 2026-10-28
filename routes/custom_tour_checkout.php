<?php

use App\Http\Controllers\TourItineraryController;
use Illuminate\Support\Facades\Route;

Route::middleware('customer.auth')->group(function () {
    Route::post('/account/itinerary-consultations/{consultation}/accept', [TourItineraryController::class, 'accept'])
        ->middleware('throttle:10,1')->name('itinerary-consultations.accept');
    Route::post('/account/itinerary-consultations/{consultation}/reject', [TourItineraryController::class, 'reject'])
        ->middleware('throttle:10,1')->name('itinerary-consultations.reject');
});
