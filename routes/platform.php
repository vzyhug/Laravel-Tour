<?php

use App\Http\Controllers\Admin\PlatformController as AdminPlatformController;
use App\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::get('/services/{type}', [PlatformController::class, 'index'])->whereIn('type', ['stay', 'bus', 'transfer', 'rental', 'activity'])->name('platform.index');
Route::get('/service-products/{product}', [PlatformController::class, 'show'])->name('platform.products.show');
Route::get('/service-trips/{trip}/seats', [PlatformController::class, 'seats'])->middleware('throttle:120,1')->name('platform.trips.seats');
Route::post('/demo/provider/callback', [PlatformController::class, 'callback'])->middleware('throttle:120,1')->name('platform.callback');

Route::middleware('customer.auth')->group(function () {
    Route::post('/service-options/{option}/quotes', [PlatformController::class, 'quote'])->middleware('throttle:30,1')->name('platform.quotes.store');
    Route::get('/service-quotes/{quote}', [PlatformController::class, 'quoteForm'])->name('platform.quotes.show');
    Route::post('/service-quotes/{quote}/orders', [PlatformController::class, 'reserve'])->name('platform.orders.store');
    Route::get('/account/services', [PlatformController::class, 'orders'])->name('platform.orders.index');
    Route::get('/account/services/{order}', [PlatformController::class, 'order'])->name('platform.orders.show');
    Route::post('/account/services/{order}/pay', [PlatformController::class, 'pay'])->middleware('throttle:12,1')->name('platform.orders.pay');
    Route::post('/account/services/{order}/cancel', [PlatformController::class, 'cancel'])->name('platform.orders.cancel');
    Route::get('/account/services/{order}/documents/{document}', [PlatformController::class, 'document'])->whereIn('document', ['voucher', 'receipt'])->name('platform.orders.document');
});

Route::prefix('admin/services')->name('admin.platform.')->middleware(['admin.auth', 'admin.role:Admin,Sale'])->group(function () {
    Route::get('/products', [AdminPlatformController::class, 'products'])->name('products.index');
    Route::post('/suppliers', [AdminPlatformController::class, 'supplier'])->name('suppliers.store');
    Route::post('/products', [AdminPlatformController::class, 'saveProduct'])->name('products.store');
    Route::get('/products/{product}', [AdminPlatformController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminPlatformController::class, 'saveProduct'])->name('products.update');
    Route::post('/products/{product}/resources', [AdminPlatformController::class, 'resource'])->name('resources.store');
    Route::post('/products/{product}/options', [AdminPlatformController::class, 'option'])->name('options.store');
    Route::post('/resources/{resource}/calendar', [AdminPlatformController::class, 'calendar'])->name('calendar.store');
    Route::post('/resources/{resource}/trips', [AdminPlatformController::class, 'trip'])->name('trips.store');
    Route::patch('/trips/{trip}', [AdminPlatformController::class, 'toggleTrip'])->name('trips.update');
    Route::get('/orders', [AdminPlatformController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [AdminPlatformController::class, 'order'])->name('orders.show');
    Route::post('/orders/{order}/actions', [AdminPlatformController::class, 'action'])->name('orders.action');
});
