<?php

use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('customer.auth')->post('/payment-attempts/{attempt}/resolve-demo', [PaymentController::class, 'resolve'])->name('payments.demo.resolve');
Route::prefix('admin')->name('admin.')->middleware(['admin.auth', 'admin.role:Admin'])->group(function () {
    Route::post('/payments/reconcile-demo', [AdminPaymentController::class, 'reconcile'])->name('payments.reconcile');
    Route::post('/payment-refunds/{refund}/retry-demo', [AdminPaymentController::class, 'retryRefund'])->name('payments.refunds.retry');
});
