<?php

namespace App\Http\Controllers;

use App\Actions\ChangeBookingDeparture;
use App\Actions\ExpireBooking;
use App\Models\Booking;
use App\Models\TourDeparture;
use Illuminate\Http\Request;

class BookingLifecycleController extends Controller
{
    public function index()
    {
        $bookings = Booking::query()->where('customer_id', (int) session('customer_id'))->with(['departure.tour', 'payment'])
            ->orderByDesc('booking_id')->paginate(15);

        return view('customer.booking-lifecycle', compact('bookings'));
    }

    public function edit(Booking $booking, ExpireBooking $expire)
    {
        abort_unless((int) $booking->customer_id === (int) session('customer_id'), 403);
        $expire->handle($booking);
        $booking->refresh()->load(['departure.tour', 'payment', 'changes.fromDeparture.tour', 'changes.toDeparture.tour']);
        $departures = TourDeparture::query()->where('is_private', false)->where('status', 'open')
            ->whereDate('departure_date', '>', today())->where('available_seats', '>=', $booking->total_adults + $booking->total_children)
            ->where('departure_id', '!=', $booking->departure_id)->whereHas('tour', fn ($q) => $q->where('is_active', true))
            ->with('tour')->orderBy('departure_date')->get();

        return view('customer.booking-change', compact('booking', 'departures'));
    }

    public function update(Request $request, Booking $booking, ChangeBookingDeparture $change, ExpireBooking $expire)
    {
        abort_unless((int) $booking->customer_id === (int) session('customer_id'), 403);
        $data = $request->validate([
            'departure_id' => ['required', 'integer', 'exists:tour_departures,departure_id'],
            'expected_departure_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
            'accept_price' => ['accepted'],
        ]);
        $expire->handle($booking);
        $change->handle($booking, (int) session('customer_id'), (int) $data['departure_id'], (int) $data['expected_departure_id'], $data['reason']);

        return redirect()->route('customer.booking-changes.edit', $booking)
            ->with('success', 'Đã đổi lịch. Điểm đón chuyển về điểm tập trung của tour mới; thời hạn giữ chỗ giữ nguyên.');
    }
}
