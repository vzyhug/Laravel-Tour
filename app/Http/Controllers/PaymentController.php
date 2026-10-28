<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\DemoQrPayment;
use App\Services\Payments\SePaySandbox;
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

        if (in_array($booking->status, self::CLOSED_STATUSES, true)) {
            return redirect()->route('customer.bookings.show', $booking);
        }

        return view('booking.payment', ['booking' => $booking->load('departure.tour', 'passengers', 'payment')]);
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->owned($booking);
        $data = $request->validate(['payment_method' => ['required', 'in:sepay,bank_transfer,cash,momo,card']]);

        if ($data['payment_method'] === 'sepay') {
            $intent = app(SePaySandbox::class)->start($booking);

            return redirect()->route('payments.sepay.show', [$booking, $intent]);
        }

        if ($data['payment_method'] === 'bank_transfer') {
            $intent = app(DemoQrPayment::class)->start($booking);

            return redirect()->route('payments.qr.show', [$booking, $intent]);
        }

        DB::transaction(function () use ($booking, $data) {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            abort_if(in_array($lockedBooking->status, self::CLOSED_STATUSES, true), 409, 'Đơn hàng đã đóng, không thể thanh toán.');
            abort_if($lockedBooking->payment()->exists(), 409, 'Đơn hàng đã được thanh toán.');
            abort_if($lockedBooking->paymentIntents()->where('status', 'review')->exists(), 409, 'Đơn có giao dịch cần kiểm tra.');
            Payment::create(['booking_id' => $lockedBooking->getKey(), 'amount' => $lockedBooking->total_amount, 'payment_method' => $data['payment_method'], 'transaction_code' => 'TXN-'.Str::upper(Str::random(12)), 'status' => 'completed']);
            $lockedBooking->update(['status' => 'confirmed']);
            $lockedBooking->paymentIntents()->where('status', 'pending')->update(['status' => 'cancelled']);
            Bill::create(['bill_number' => 'INV-'.now()->format('Ymd').'-'.$lockedBooking->getKey(), 'booking_id' => $lockedBooking->getKey(), 'final_amount' => $lockedBooking->total_amount]);
        });

        return redirect()->route('customer.bookings.show', $booking)->with('success', 'Thanh toán thành công.');
    }
}
