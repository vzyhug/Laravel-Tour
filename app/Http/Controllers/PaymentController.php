<?php

namespace App\Http\Controllers;

use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Services\Payments\DemoQrPayment;
use App\Services\Payments\MomoPaymentGateway;
use App\Services\Payments\PaymentWorkflow;
use App\Services\Payments\SePaySandbox;
use App\Services\Payments\VnpayPaymentGateway;
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

        $booking->load([
            'departure.tour', 'passengers', 'payment.attempts.momoSession',
            'payment.attempts.vnpaySession', 'payment.refunds', 'paymentIntents',
        ]);
        $pendingQr = DemoQrPayment::enabled()
            ? $booking->paymentIntents->first(fn ($intent) => $intent->provider === 'vietqr_demo' && $intent->status === 'pending' && $intent->expires_at->isFuture())
            : null;
        $pendingSePay = SePaySandbox::ready()
            ? $booking->paymentIntents->first(fn ($intent) => $intent->provider === SePaySandbox::PROVIDER && in_array($intent->status, ['pending', 'review'], true))
            : null;
        $pendingMomo = $booking->payment?->attempts->first(fn ($attempt) => $attempt->provider === 'momo' && $attempt->status === 'pending')?->momoSession;
        $pendingVnpay = $booking->payment?->attempts->first(fn ($attempt) => $attempt->provider === 'vnpay' && $attempt->status === 'pending')?->vnpaySession;

        return view('booking.payment', [
            'booking' => $booking,
            'attemptKey' => (string) Str::uuid(),
            'pendingQr' => $pendingQr,
            'pendingSePay' => $pendingSePay,
            'hasPaymentReview' => $booking->paymentIntents->contains('status', 'review'),
            'pendingMomo' => $pendingMomo,
            'momoReady' => MomoPaymentGateway::ready(),
            'pendingVnpay' => $pendingVnpay,
            'vnpayReady' => VnpayPaymentGateway::ready(),
        ]);
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->owned($booking);
        $data = $request->validate([
            'payment_method' => ['required', 'in:sepay,vietqr_demo,bank_transfer,momo,cash,card'],
            'idempotency_key' => ['nullable', 'uuid'],
            'demo_outcome' => ['nullable', 'in:succeeded,failed,pending'],
        ]);

        app(ExpireBooking::class)->handle($booking);
        abort_if($booking->paymentIntents()->where('status', 'review')->exists(), 409, 'Giao dịch cần được nhân viên đối soát trước khi thử lại.');
        $key = $data['idempotency_key'] ?? (string) Str::uuid();

        if ($data['payment_method'] === 'bank_transfer') {
            abort_unless(VnpayPaymentGateway::ready(), 503, 'Thanh toán ngân hàng hiện chưa khả dụng. Hãy cấu hình VNPay Sandbox rồi thử lại.');
            $paymentUrl = app(VnpayPaymentGateway::class)->start($booking, $key, (string) $request->ip());

            return redirect()->away($paymentUrl);
        }

        if ($data['payment_method'] === 'momo') {
            abort_unless(MomoPaymentGateway::ready(), 503, 'MoMo Sandbox chưa được cấu hình.');
            $session = app(MomoPaymentGateway::class)->start($booking, $key);

            return redirect()->route('payments.momo.show', [$booking, $session]);
        }

        if ($data['payment_method'] === 'sepay') {
            $intent = app(SePaySandbox::class)->start($booking);

            return redirect()->route('payments.sepay.show', [$booking, $intent]);
        }

        if ($data['payment_method'] === 'vietqr_demo') {
            $intent = app(DemoQrPayment::class)->start($booking);

            return redirect()->route('payments.qr.show', [$booking, $intent]);
        }

        $request->validate([
            'demo_outcome' => ['required', 'in:succeeded,failed,pending'],
        ], [
            'demo_outcome.required' => 'Hãy chọn kết quả cho giao dịch mô phỏng nội bộ.',
        ]);
        $attempt = app(PaymentWorkflow::class)->pay($booking, $data['payment_method'], $key, $data['demo_outcome']);

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
        abort_unless($attempt->provider === 'mock', 403, 'Kết quả giao dịch từ cổng thanh toán phải được xác nhận qua callback đã ký.');
        $data = $request->validate(['outcome' => ['required', 'in:succeeded,failed']]);
        app(ExpireBooking::class)->handle($booking);
        app(PaymentWorkflow::class)->settle($attempt, $data['outcome']);

        return redirect()->route('customer.bookings.show', $booking)->with('success', 'Đã xử lý kết quả mô phỏng.');
    }
}