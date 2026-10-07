<?php

namespace App\Http\Controllers;

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
    private function owned(Booking $booking): void
    {
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
    }

    public function create(Booking $booking): View
    {
        $this->owned($booking);

        return view('booking.payment', ['booking' => $booking->load('departure.tour', 'passengers', 'payment')]);
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->owned($booking);
        $data = $request->validate(['payment_method' => ['required', 'in:bank_transfer,cash,momo,card']]);

        DB::transaction(function () use ($booking, $data) {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            abort_if($lockedBooking->payment()->exists(), 409, 'Đơn hàng đã được thanh toán.');
            Payment::create(['booking_id' => $lockedBooking->getKey(), 'amount' => $lockedBooking->total_amount, 'payment_method' => $data['payment_method'], 'transaction_code' => 'TXN-'.Str::upper(Str::random(12)), 'status' => 'completed']);
            $lockedBooking->update(['status' => 'confirmed']);
            Bill::create(['bill_number' => 'INV-'.now()->format('Ymd').'-'.$lockedBooking->getKey(), 'booking_id' => $lockedBooking->getKey(), 'final_amount' => $lockedBooking->total_amount]);
        });

        return redirect()->route('customer.bookings.show', $booking)->with('success', 'Thanh toán thành công.');
    }
}
