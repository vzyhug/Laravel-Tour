<?php

namespace App\Http\Controllers;

use App\Actions\CreateBooking;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Coupon;
use App\Models\TourDeparture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
        public function create(TourDeparture $departure): View
    {
        abort_unless($departure->status === 'open' && $departure->available_seats > 0, 404);

        $customerId = (int) session('customer_id');
        $coupons = Coupon::all()
            ->filter(fn (Coupon $coupon) => $coupon->check($customerId, (float) $coupon->min_order) === null)
            ->values();

        return view('booking.create', ['departure' => $departure->load('tour.images'), 'coupons' => $coupons]);
    }

    public function store(StoreBookingRequest $request, CreateBooking $createBooking): RedirectResponse
    {
        $booking = $createBooking->handle((int) session('customer_id'), $request->validated());

        return redirect()->route('payments.create', $booking)->with('success', 'Đã giữ chỗ. Vui lòng hoàn tất thanh toán.');
    }

    // Kiểm tra mã giảm giá để hiện số tiền giảm trước khi đặt (kết quả cuối cùng vẫn do store() quyết định)
    public function checkCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departure_id' => ['required', 'integer', 'exists:tour_departures,departure_id'],
            'adults' => ['required', 'integer', 'min:0', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        $departure = TourDeparture::findOrFail($data['departure_id']);
        $subtotal = ($data['adults'] * (float) $departure->adult_price) + ($data['children'] * (float) $departure->child_price);

        $coupon = Coupon::where('code', Coupon::normalize($data['code']))->first();
        $error = $coupon ? $coupon->check((int) session('customer_id'), $subtotal) : 'Mã giảm giá không tồn tại.';

        if ($error) {
            return response()->json(['valid' => false, 'message' => $error]);
        }

        $discount = $coupon->discountFor($subtotal);

        return response()->json([
            'valid' => true,
            'message' => 'Áp dụng mã thành công.',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
        ]);
    }
}
