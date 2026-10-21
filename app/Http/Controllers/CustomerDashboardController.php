<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Review;
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

    public function index(): View
    {
        $bookings = $this->customer()->bookings()->with(['departure.tour.images', 'payment'])->orderByDesc('booking_date')->paginate(8);

        return view('customer.dashboard', compact('bookings'));
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
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('customers', 'email')->ignore($customer->getKey(), 'customer_id')],
            'phone' => ['required', 'regex:/^(0|\+84)[0-9]{9}$/', Rule::unique('customers', 'phone')->ignore($customer->getKey(), 'customer_id')],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'full_name.required' => 'Họ và tên không được để trống.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email này đã được sử dụng bởi tài khoản khác.',
            'phone.required' => 'Số điện thoại không được để trống.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam (VD: 0901234567).',
            'phone.unique' => 'Số điện thoại này đã được sử dụng bởi tài khoản khác.',
        ]);
        $customer->update($data);
        session(['customer_name' => $customer->full_name]);

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function review(Request $request, Booking $booking): RedirectResponse
    {
        $this->owned($booking);
        $booking->loadMissing('departure');

        $canReview = $booking->status === 'completed' || ($booking->status === 'confirmed' && $booking->departure->return_date->isPast());
        abort_unless($canReview, 403, 'Chuyến đi chưa kết thúc, bạn chưa thể đánh giá.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'rating.required' => 'Vui lòng chọn số sao đánh giá.',
            'rating.between' => 'Số sao đánh giá phải từ 1 đến 5.',
            'feedback.required' => 'Vui lòng chia sẻ cảm nhận về chuyến đi.',
            'feedback.min' => 'Cảm nhận phải có ít nhất 5 ký tự.',
            'feedback.max' => 'Cảm nhận không được vượt quá 2000 ký tự.',
        ]);

        Review::updateOrCreate(['customer_id' => $booking->customer_id, 'tour_id' => $booking->departure->tour_id], $data);

        return back()->with('success', 'Cảm ơn bạn đã đánh giá chuyến đi.');
    }
}
