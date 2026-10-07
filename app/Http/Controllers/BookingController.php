<?php

namespace App\Http\Controllers;

use App\Actions\CreateBooking;
use App\Http\Requests\StoreBookingRequest;
use App\Models\TourDeparture;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function create(TourDeparture $departure): View
    {
        abort_unless($departure->status === 'open' && $departure->available_seats > 0, 404);

        return view('booking.create', ['departure' => $departure->load('tour.images')]);
    }

    public function store(StoreBookingRequest $request, CreateBooking $createBooking): RedirectResponse
    {
        $booking = $createBooking->handle((int) session('customer_id'), $request->validated());

        return redirect()->route('payments.create', $booking)->with('success', 'Đã giữ chỗ. Vui lòng hoàn tất thanh toán.');
    }
}
