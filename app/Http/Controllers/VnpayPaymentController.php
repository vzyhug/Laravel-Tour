<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\VnpayPaymentSession;
use App\Services\Payments\VnpayPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VnpayPaymentController extends Controller
{
    public function returned(Request $request, VnpayPaymentGateway $gateway): RedirectResponse
    {
        $request->validate(['vnp_TxnRef' => ['required', 'string', 'max:100']]);
        $session = VnpayPaymentSession::query()
            ->with('attempt.payment.booking')
            ->where('txn_ref', (string) $request->query('vnp_TxnRef'))
            ->firstOrFail();
        $booking = $session->attempt->payment->booking;
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);

        $session = $gateway->settleReturn($request->query());

        if ($session->status === 'succeeded') {
            return redirect()->route('customer.bookings.show', $booking)
                ->with('success', 'VNPay đã xác nhận thanh toán thành công.');
        }

        return redirect()->route('payments.create', $booking)
            ->with('error', 'VNPay không hoàn tất giao dịch. Bạn có thể thử thanh toán lại.');
    }

    public function resume(Booking $booking, VnpayPaymentSession $session): RedirectResponse
    {
        $session->loadMissing('attempt.payment');
        abort_unless((int) $session->attempt->payment->booking_id === (int) $booking->getKey(), 404);
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
        abort_unless($session->status === 'pending', 409, 'Phiên VNPay này đã được đóng.');

        return redirect()->away($session->payment_url);
    }

    public function restart(Request $request, Booking $booking, VnpayPaymentSession $session, VnpayPaymentGateway $gateway): RedirectResponse
    {
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
        abort_unless((int) $session->attempt()->firstOrFail()->payment->booking_id === (int) $booking->getKey(), 404);

        $paymentUrl = $gateway->restart($booking, $session, (string) Str::uuid(), (string) $request->ip());

        return redirect()->away($paymentUrl);
    }
}
