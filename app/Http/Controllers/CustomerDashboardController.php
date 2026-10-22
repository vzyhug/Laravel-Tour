<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerDashboardController extends Controller
{
    private function customer(): Customer
    {
        return Customer::findOrFail(session('customer_id'));
    }

    private function owned(Booking $booking): void
    {
        abort_unless($booking->customer_id === (int) session('customer_id'), 403);
    }

    public function index(Request $request): View
    {
        $customer = $this->customer();
        $tab = in_array($request->input('tab'), ['upcoming', 'saved', 'history'], true)
            ? $request->input('tab') : 'upcoming';
        $upcomingBookings = $customer->bookings()->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('departure', fn (Builder $query) => $query->whereDate('return_date', '>=', today()));
        $historyBookings = $customer->bookings()->where(function (Builder $query): void {
            $query->whereNotIn('status', ['pending', 'confirmed'])
                ->orWhereHas('departure', fn (Builder $departureQuery) => $departureQuery->whereDate('return_date', '<', today()));
        });
        $summary = [
            'upcoming' => (clone $upcomingBookings)->count(),
            'completed' => $customer->bookings()->where('status', 'completed')->count(),
            'history' => (clone $historyBookings)->count(),
            'saved' => $customer->favoriteTours()->active()->count(),
            'total' => $customer->bookings()->count(),
        ];
        $bookings = ($tab === 'history' ? $historyBookings : $upcomingBookings)
            ->with(['departure.tour.images', 'departure.guide', 'payment', 'bill'])
            ->withCount('passengers')->orderByDesc('booking_date')->paginate(8)->withQueryString();
        $favoriteTours = $customer->favoriteTours()->active()->with(['province', 'category', 'images'])->limit(6)->get();

        return view('customer.dashboard', compact('customer', 'tab', 'summary', 'bookings', 'favoriteTours'));
    }

    public function show(Booking $booking): View
    {
        $this->owned($booking);

        return view('customer.booking-show', ['booking' => $booking->load(['departure.tour', 'passengers', 'payment', 'bill'])]);
    }

    public function invoice(Booking $booking): View
    {
        $this->owned($booking);
        abort_unless($booking->bill()->exists(), 404);

        return view('customer.invoice', ['booking' => $booking->load(['customer', 'departure.tour', 'passengers', 'payment', 'bill'])]);
    }

    public function profile(): View
    {
        return view('customer.profile', ['customer' => $this->customer()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = $this->customer();
        $data = $request->validate(['full_name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', Rule::unique('customers', 'email')->ignore($customer->getKey(), 'customer_id')], 'phone' => ['required', 'string', 'max:20'], 'address' => ['nullable', 'string', 'max:255']]);
        $customer->update($data);
        session(['customer_name' => $customer->full_name]);

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function review(Request $request, Booking $booking): RedirectResponse
    {
        $this->owned($booking);
        abort_unless(in_array($booking->status, ['confirmed', 'completed'], true), 403);
        $booking->loadMissing('departure');
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'feedback' => ['required', 'string', 'max:2000']]);
        Review::updateOrCreate(['customer_id' => $booking->customer_id, 'tour_id' => $booking->departure->tour_id], $data);

        return back()->with('success', 'Cảm ơn bạn đã đánh giá chuyến đi.');
    }
}
