<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelBooking;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelBookingController extends Controller
{
    public function index()
    {
        return view('admin.hotel-booking.index', ['types' => HotelRoomType::with('hotel')->orderBy('id')->paginate(20, ['*'], 'types_page'), 'hotels' => Hotel::orderBy('name')->get(), 'bookings' => HotelBooking::with('customer', 'roomType.hotel', 'payment')->latest()->paginate(20)]);
    }

    public function storeRoomType(Request $request)
    {
        $data = $request->validate(['hotel_id' => ['required', 'integer', 'exists:hotels,id'], 'name' => ['required', 'string', 'max:120'], 'max_guests' => ['required', 'integer', 'between:1,20'], 'is_demo' => ['sometimes', 'boolean']]);
        HotelRoomType::create($data + ['is_demo' => false]);

        return back()->with('success', 'Đã thêm loại phòng. Cần nhập tồn và giá trước khi mở bán theo ngày.');
    }

    public function inventory(Request $request, HotelRoomType $roomType)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'availability' => ['nullable', 'in:available,full'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
        ]);

        $nights = $roomType->inventory()
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('stay_date', '>=', $filters['from']))
            ->when(($filters['availability'] ?? null) === 'available', fn ($q) => $q->where('available_rooms', '>', 0))
            ->when(($filters['availability'] ?? null) === 'full', fn ($q) => $q->where('available_rooms', '<=', 0))
            ->when(isset($filters['min_price']), fn ($q) => $q->where('nightly_price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($q) => $q->where('nightly_price', '<=', $filters['max_price']))
            ->orderBy('stay_date')
            ->paginate(31);

        return view('admin.hotel-booking.inventory', [
            'type' => $roomType->load('hotel'),
            'nights' => $nights,
        ]);
    }

    public function saveInventory(Request $request, HotelRoomType $roomType)
    {
        $data = $request->validate(['stay_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'total_rooms' => ['required', 'integer', 'between:0,10000'], 'nightly_price' => ['required', 'integer', 'between:1,1000000000']]);
        DB::transaction(function () use ($roomType, $data) {
            HotelRoomType::whereKey($roomType->id)->lockForUpdate()->firstOrFail();
            $night = HotelRoomInventory::where('room_type_id', $roomType->id)->whereDate('stay_date', $data['stay_date'])->lockForUpdate()->first();
            $allocated = $night ? $night->total_rooms - $night->available_rooms : 0;
            if ($data['total_rooms'] < $allocated) {
                throw ValidationException::withMessages(['total_rooms' => 'Tổng phòng không được thấp hơn số phòng đang giữ hoặc đã đặt.']);
            }
            HotelRoomInventory::updateOrCreate(['room_type_id' => $roomType->id, 'stay_date' => $data['stay_date']], ['total_rooms' => $data['total_rooms'], 'available_rooms' => $data['total_rooms'] - $allocated, 'nightly_price' => $data['nightly_price']]);
        }, 3);

        return back()->with('success', 'Đã cập nhật tồn và giá theo đêm; giá của đơn cũ được giữ nguyên.');
    }

    public function complete(HotelBooking $hotelBooking)
    {
        DB::transaction(function () use ($hotelBooking) {
            $booking = HotelBooking::whereKey($hotelBooking->id)->lockForUpdate()->firstOrFail();
            if ($booking->status === 'completed') {
                return;
            }
            if ($booking->status !== 'confirmed' || $booking->check_out->isFuture()) {
                throw ValidationException::withMessages(['booking' => 'Chỉ hoàn thành đơn đã xác nhận và đã đến ngày trả phòng.']);
            }
            $booking->update(['status' => 'completed']);
        });

        return back()->with('success', 'Đã hoàn thành đơn lưu trú.');
    }
}
