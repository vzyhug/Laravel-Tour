<?php

namespace App\Http\Controllers;

use App\Actions\ExpireBooking;
use App\Models\Bill;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentController extends Controller
{
    // Đơn ở các trạng thái này không được thanh toán nữa
    private const CLOSED_STATUSES = ['cancelled', 'completed'];

    private function owned(Booking $booking): void
    {
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
    }

    public function create(Booking $booking): View|RedirectResponse
    {
        $this->owned($booking);
        app(ExpireBooking::class)->handle($booking);
        $booking->refresh();

        if (in_array($booking->status, self::CLOSED_STATUSES, true)) {
            return redirect()->route('customer.bookings.show', $booking);
        }

        return view('booking.payment', ['booking' => $booking->load('departure.tour', 'passengers', 'payment')]);
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->owned($booking);
        app(ExpireBooking::class)->handle($booking);
        $data = $request->validate([
            'payment_method' => ['required', 'in:bank_transfer,cash,momo,card'],
            'demo_result' => ['nullable', 'in:success,failed'],
        ]);

        DB::transaction(function () use ($booking, $data) {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            abort_if(in_array($lockedBooking->status, self::CLOSED_STATUSES, true), 409, 'Đơn hàng đã đóng, không thể thanh toán.');
            abort_if($lockedBooking->payment_expires_at?->isPast(), 409, 'Đã hết thời hạn thanh toán. Vui lòng đặt lại để kiểm tra giá và chỗ.');
            abort_if($lockedBooking->payment()->exists(), 409, 'Đơn hàng đã được thanh toán.');
            if (($data['demo_result'] ?? 'success') === 'failed') {
                $lockedBooking->recordEvent('payment_failed_demo', ['method' => $data['payment_method']]);

                return;
            }
            Payment::create(['booking_id' => $lockedBooking->getKey(), 'amount' => $lockedBooking->total_amount, 'payment_method' => $data['payment_method'], 'transaction_code' => 'TXN-'.Str::upper(Str::random(12)), 'status' => 'completed']);
            $lockedBooking->update(['status' => 'confirmed']);
            Bill::create(['bill_number' => 'INV-'.now()->format('Ymd').'-'.$lockedBooking->getKey(), 'booking_id' => $lockedBooking->getKey(), 'final_amount' => $lockedBooking->total_amount]);
            $lockedBooking->recordEvent('payment_captured_demo', ['amount_vnd' => (int) $lockedBooking->total_amount, 'method' => $data['payment_method']]);
            $lockedBooking->recordEvent('voucher_available_demo');
        });

        if (($data['demo_result'] ?? 'success') === 'failed') {
            return back()->withErrors(['payment' => 'Thanh toán mô phỏng thất bại. Chưa ghi nhận tiền; bạn có thể thử lại trong thời hạn giữ chỗ.']);
        }

        return redirect()->route('customer.bookings.show', $booking)->with('success', 'Thanh toán mô phỏng thành công. Chứng từ demo đã sẵn sàng.');
    }
}
