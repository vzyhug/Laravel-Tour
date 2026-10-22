<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ExpireBooking;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingController extends Controller
{
    // Admin chỉ được xác nhận và hoàn thành đơn, không được hủy đơn của khách
    private const TRANSITIONS = [
        'pending' => ['confirmed'],
        'confirmed' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function index(Request $request): View
    {
        $keyword = $request->string('q')->trim()->toString();

        $bookings = Booking::with(['customer', 'departure.tour'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($w) use ($keyword) {
                    $w->where('booking_code', 'like', "%{$keyword}%")
                        ->orWhereHas('customer', fn ($c) => $c
                            ->where('full_name', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%"));
                });
            })
            ->orderByDesc('booking_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', ['bookings' => $bookings]);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['customer', 'departure.tour', 'passengers', 'payment']);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'nextStatuses' => self::TRANSITIONS[$booking->status] ?? [],
        ]);
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        app(ExpireBooking::class)->handle($booking);
        $data = $request->validate([
            'status' => ['required', 'in:confirmed,completed'],
        ]);

        $error = DB::transaction(function () use ($booking, $data) {
            $booking = Booking::with('departure')->lockForUpdate()->findOrFail($booking->getKey());

            if ($booking->status === 'pending' && $booking->payment_expires_at?->isPast()) {
                return 'Đơn đã hết hạn giữ chỗ, không thể xác nhận.';
            }

            if (! in_array($data['status'], self::TRANSITIONS[$booking->status] ?? [], true)) {
                return 'Không thể chuyển đơn sang trạng thái này.';
            }

            if ($data['status'] === 'completed' && $booking->departure->departure_date->isFuture()) {
                return 'Chuyến đi chưa khởi hành nên chưa thể hoàn thành đơn.';
            }

            $booking->update(['status' => $data['status']]);
            $booking->recordEvent('admin_status_changed', ['status' => $data['status'], 'employee_id' => (int) session('employee_id')]);

            return null;
        });

        if ($error) {
            return back()->withErrors(['status' => $error]);
        }

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Đã cập nhật trạng thái đơn.');
    }
}
