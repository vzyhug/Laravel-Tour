<?php

namespace App\Http\Controllers;

use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Services\Payments\DemoQrPayment;
use App\Services\Payments\PaymentWorkflow;
use App\Services\Payments\SePaySandbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentController extends Controller
{
    private function owned(Booking $booking): void
    {
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
    }

    public function create(Booking $booking): View|RedirectResponse
    {
        $this->owned($booking);
        app(ExpireBooking::class)->handle($booking);
        $booking->refresh();
        if (! in_array($booking->status, ['pending', 'confirmed'], true)) {
            return redirect()->route('customer.bookings.show', $booking);
        }

        $booking->load(['departure.tour', 'passengers', 'payment.attempts', 'payment.refunds', 'paymentIntents']);
        $pendingQr = DemoQrPayment::enabled()
            ? $booking->paymentIntents->first(fn ($intent) => $intent->provider === 'vietqr_demo' && $intent->status === 'pending' && $intent->expires_at->isFuture())
            : null;
        $pendingSePay = SePaySandbox::enabled()
            ? $booking->paymentIntents->first(fn ($intent) => $intent->provider === SePaySandbox::PROVIDER && in_array($intent->status, ['pending', 'review'], true))
            : null;

        return view('booking.payment', [
            'booking' => $booking,
            'attemptKey' => (string) Str::uuid(),
            'pendingQr' => $pendingQr,
            'pendingSePay' => $pendingSePay,
            'hasPaymentReview' => $booking->paymentIntents->contains('status', 'review'),
        ]);
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->owned($booking);
        $data = $request->validate([
            'payment_method' => ['required', 'in:sepay,bank_transfer,cash,momo,card'],
            'idempotency_key' => ['nullable', 'uuid'],
            'demo_outcome' => ['nullable', 'in:succeeded,failed,pending'],
        ]);
        app(ExpireBooking::class)->handle($booking);

        if ($data['payment_method'] === 'sepay') {
            $intent = app(SePaySandbox::class)->start($booking);

            return redirect()->route('payments.sepay.show', [$booking, $intent]);
        }

        if ($data['payment_method'] === 'bank_transfer' && DemoQrPayment::enabled()) {
            $intent = app(DemoQrPayment::class)->start($booking);

            return redirect()->route('payments.qr.show', [$booking, $intent]);
        }

        if ($data['payment_method'] === 'bank_transfer') {
            // Preserve the former classroom transfer simulation when the new QR
            // feature is disabled; QR-only requests without that legacy field stay disabled.
            abort_unless(array_key_exists('demo_outcome', $data), 404);
        }

        // Keep the established mock payment workflow when QR demo is disabled,
        // and for cash, MoMo, and card classroom scenarios.
        $attempt = app(PaymentWorkflow::class)->pay(
            $booking,
            $data['payment_method'],
            $data['idempotency_key'] ?? (string) Str::uuid(),
            $data['demo_outcome'] ?? 'succeeded'
        );

        return redirect()->route($attempt->status === 'succeeded' ? 'customer.bookings.show' : 'payments.create', $booking)->with('success', match ($attempt->status) {
            'succeeded' => 'Thanh toán mô phỏng thành công.',
            'failed' => 'Thanh toán mô phỏng thất bại. Bạn có thể thử lại.',
            default => 'Giao dịch mô phỏng đang chờ xử lý.',
        });
    }

    public function resolve(Request $request, PaymentAttempt $attempt): RedirectResponse
    {
        $booking = $attempt->payment->booking;
        $this->owned($booking);
        $data = $request->validate(['outcome' => ['required', 'in:succeeded,failed']]);
        app(ExpireBooking::class)->handle($booking);
        app(PaymentWorkflow::class)->settle($attempt, $data['outcome']);

        return redirect()->route('customer.bookings.show', $booking)->with('success', 'Đã xử lý kết quả mô phỏng.');
    }
}
