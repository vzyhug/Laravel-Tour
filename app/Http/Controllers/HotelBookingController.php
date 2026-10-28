<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelBooking;
use App\Services\HotelBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HotelBookingController extends Controller
{
    private function owned(HotelBooking $hotelBooking): void
    {
        abort_unless($hotelBooking->customer_id === (int) session('customer_id'), 403);
    }

    public function search(Request $request, Hotel $hotel, HotelBookingService $service)
    {
        $hotel->load(['images', 'destination']);
        $data = $request->validate(['check_in' => ['nullable', 'required_with:check_out', 'date_format:Y-m-d'], 'check_out' => ['nullable', 'required_with:check_in', 'date_format:Y-m-d'], 'rooms' => ['sometimes', 'integer', 'between:1,10'], 'guests' => ['sometimes', 'integer', 'between:1,100']]);
        $data += ['rooms' => 1, 'guests' => 1];
        $offers = collect();
        if (! empty($data['check_in']) && ! empty($data['check_out'])) {
            $dates = $service->dates($data['check_in'], $data['check_out']);
            $offers = $hotel->roomTypes()->where('is_active', true)->with(['inventory' => fn ($q) => $q->whereIn('stay_date', $dates)->orderBy('stay_date')])->get()
                ->filter(fn ($type) => $type->max_guests * $data['rooms'] >= $data['guests'] && $type->inventory->count() === count($dates) && $type->inventory->every(fn ($night) => $night->available_rooms >= $data['rooms']));
        }

        return view('hotel-booking.search', ['hotel' => $hotel, 'data' => $data, 'offers' => $offers, 'requestKey' => (string) Str::uuid()]);
    }

    public function store(Request $request, HotelBookingService $service)
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'room_type_id' => ['required', 'integer', 'exists:hotel_room_types,id'], 'check_in' => ['required', 'date_format:Y-m-d'], 'check_out' => ['required', 'date_format:Y-m-d'], 'rooms' => ['required', 'integer', 'between:1,10'], 'guests' => ['required', 'integer', 'between:1,100']]);
        $booking = $service->hold((int) session('customer_id'), $data);

        return redirect()->route('hotel-bookings.show', $booking);
    }

    public function index()
    {
        return view('hotel-booking.index', ['bookings' => HotelBooking::where('customer_id', (int) session('customer_id'))->with('roomType.hotel')->latest()->paginate(15)]);
    }

    public function show(HotelBooking $hotelBooking)
    {
        $this->owned($hotelBooking);

        return view('hotel-booking.show', ['booking' => $hotelBooking->load('roomType.hotel', 'nights', 'payment')]);
    }

    public function pay(HotelBooking $hotelBooking, HotelBookingService $service)
    {
        $this->owned($hotelBooking);
        $paid = $service->pay($hotelBooking);

        return back()->with('success', $paid ? 'Đã xác nhận thanh toán mô phỏng. Không thu tiền thật.' : 'Đơn không còn hiệu lực để thanh toán.');
    }

    public function cancel(HotelBooking $hotelBooking, HotelBookingService $service)
    {
        $this->owned($hotelBooking);
        $service->cancel($hotelBooking);

        return back()->with('success', 'Đã xử lý hủy đơn. Nếu có thanh toán, hoàn tiền cũng chỉ là mô phỏng.');
    }
}
