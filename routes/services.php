<?php

use App\Http\Controllers\Admin\TravelServiceController as AdminTravelServiceController;
use App\Http\Controllers\TravelServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/services/{type}', [TravelServiceController::class, 'index'])->whereIn('type', ['stay', 'bus', 'transfer', 'rental', 'activity'])->name('services.index');
Route::get('/service-products/{product}', [TravelServiceController::class, 'show'])->name('services.products.show');
Route::get('/service-trips/{trip}/seats', [TravelServiceController::class, 'seats'])->middleware('throttle:120,1')->name('services.trips.seats');
Route::post('/demo/provider/callback', [TravelServiceController::class, 'callback'])->middleware('throttle:120,1')->name('services.callback');

Route::middleware('customer.auth')->group(function () {
    Route::post('/service-options/{option}/quotes', [TravelServiceController::class, 'quote'])->middleware('throttle:30,1')->name('services.quotes.store');
    Route::get('/service-quotes/{quote}', [TravelServiceController::class, 'quoteForm'])->name('services.quotes.show');
    Route::post('/service-quotes/{quote}/orders', [TravelServiceController::class, 'reserve'])->name('services.orders.store');
    Route::get('/account/services', [TravelServiceController::class, 'orders'])->name('services.orders.index');
    Route::get('/account/services/{order}', [TravelServiceController::class, 'order'])->name('services.orders.show');
    Route::post('/account/services/{order}/pay', [TravelServiceController::class, 'pay'])->middleware('throttle:12,1')->name('services.orders.pay');
    Route::post('/account/services/{order}/cancel', [TravelServiceController::class, 'cancel'])->name('services.orders.cancel');
    Route::get('/account/services/{order}/documents/{document}', [TravelServiceController::class, 'document'])->whereIn('document', ['voucher', 'receipt'])->name('services.orders.document');
});

Route::prefix('admin/services')->name('admin.services.')->middleware(['admin.auth', 'admin.role:Admin,Sale'])->group(function () {
    Route::get('/products', [AdminTravelServiceController::class, 'products'])->name('products.index');
    Route::post('/suppliers', [AdminTravelServiceController::class, 'supplier'])->name('suppliers.store');
    Route::post('/products', [AdminTravelServiceController::class, 'saveProduct'])->name('products.store');
    Route::get('/products/{product}', [AdminTravelServiceController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminTravelServiceController::class, 'saveProduct'])->name('products.update');
    Route::post('/products/{product}/resources', [AdminTravelServiceController::class, 'resource'])->name('resources.store');
    Route::post('/products/{product}/options', [AdminTravelServiceController::class, 'option'])->name('options.store');
    Route::post('/resources/{resource}/calendar', [AdminTravelServiceController::class, 'calendar'])->name('calendar.store');
    Route::post('/resources/{resource}/trips', [AdminTravelServiceController::class, 'trip'])->name('trips.store');
    Route::patch('/trips/{trip}', [AdminTravelServiceController::class, 'toggleTrip'])->name('trips.update');
    Route::get('/orders', [AdminTravelServiceController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [AdminTravelServiceController::class, 'order'])->name('orders.show');
    Route::post('/orders/{order}/actions', [AdminTravelServiceController::class, 'action'])->name('orders.action');
});
