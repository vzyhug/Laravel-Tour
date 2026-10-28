<?php

namespace App\Http\Controllers;

use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Services\Payments\MomoPaymentGateway;
use App\Services\Payments\PaymentWorkflow;
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

        $booking->load('departure.tour', 'passengers', 'payment.attempts.momoSession', 'payment.attempts.vnpaySession', 'payment.refunds');
        $pendingMomo = $booking->payment?->attempts->first(fn ($attempt) => $attempt->provider === 'momo' && $attempt->status === 'pending')?->momoSession;
        $pendingVnpay = $booking->payment?->attempts->first(fn ($attempt) => $attempt->provider === 'vnpay' && $attempt->status === 'pending')?->vnpaySession;

        return view('booking.payment', [
            'booking' => $booking,
            'attemptKey' => (string) Str::uuid(),
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
            'payment_method' => ['required', 'in:bank_transfer,cash,momo,card'],
            'idempotency_key' => ['nullable', 'uuid'],
            'demo_outcome' => ['nullable', 'in:succeeded,failed,pending'],
        ]);
        if ($data['payment_method'] === 'bank_transfer') {
            abort_unless(VnpayPaymentGateway::ready(), 503, 'Thanh toán ngân hàng hiện chưa khả dụng. Hãy cấu hình VNPay Sandbox rồi thử lại.');
            app(ExpireBooking::class)->handle($booking);
            $key = $data['idempotency_key'] ?? (string) Str::uuid();
            $paymentUrl = app(VnpayPaymentGateway::class)->start($booking, $key, (string) $request->ip());

            return redirect()->away($paymentUrl);
        }

        $usesLiveGateway = $data['payment_method'] === 'momo' && MomoPaymentGateway::ready();
        if (! $usesLiveGateway) {
            $request->validate([
                'demo_outcome' => ['required', 'in:succeeded,failed,pending'],
            ], [
                'demo_outcome.required' => 'Hãy chọn kết quả cho giao dịch mô phỏng.',
            ]);
        }
        app(ExpireBooking::class)->handle($booking);
        $key = $data['idempotency_key'] ?? (string) Str::uuid();
        if ($data['payment_method'] === 'momo') {
            $session = app(MomoPaymentGateway::class)->start($booking, $key);

            return redirect()->route('payments.momo.show', [$booking, $session]);
        }
        $attempt = app(PaymentWorkflow::class)->pay($booking, $data['payment_method'], $key, $data['demo_outcome'] ?? 'succeeded');

        return redirect()->route($attempt->status === 'succeeded' ? 'customer.bookings.show' : 'payments.create', $booking)->with('success', match ($attempt->status) {
            'succeeded' => 'Thanh toán mô phỏng thành công.', 'failed' => 'Thanh toán mô phỏng thất bại. Bạn có thể thử lại.', default => 'Giao dịch mô phỏng đang chờ xử lý.',
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
