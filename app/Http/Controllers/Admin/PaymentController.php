<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentReconciliation;
use App\Models\PaymentRefund;
use App\Services\Payments\ReconcilePayments;
use App\Services\Payments\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        return view('admin.payments.index', [
            'payments' => Payment::with(['booking.customer', 'booking.departure.tour', 'refunds', 'attempts'])->orderByDesc('payment_date')->paginate(15),
            'reconciliations' => PaymentReconciliation::latest('id')->limit(20)->get(),
        ]);
    }

    public function reconcile(ReconcilePayments $service): RedirectResponse
    {
        $count = $service->handle();

        return back()->with('success', 'Đối soát demo hoàn tất: '.$count.' giao dịch cần kiểm tra.');
    }

    public function retryRefund(PaymentRefund $refund, RefundService $service): RedirectResponse
    {
        $result = $service->process($refund);

        return back()->with('success', $result->status === 'succeeded' ? 'Đã hoàn tiền mô phỏng thành công.' : 'Hoàn tiền mô phỏng thất bại; có thể thử lại.');
    }

    public function show(\App\Models\Payment $payment): \Illuminate\View\View
    {
        $payment->load(['booking.customer', 'booking.departure.tour', 'attempts', 'refunds']);

        return view('admin.payments.show', ['payment' => $payment]);
    }
}
