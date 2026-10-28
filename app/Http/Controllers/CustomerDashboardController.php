<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Review;
use App\Models\TourDeparture;
use App\Services\Payments\DemoQrPayment;
use App\Services\Payments\RefundService;
use App\Services\Payments\SePaySandbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $pendingQr = DemoQrPayment::enabled()
            ? $booking->paymentIntents()->where('provider', 'vietqr_demo')->where('status', 'pending')->where('expires_at', '>', now())->latest('id')->first()
            : null;

        $pendingSePay = SePaySandbox::enabled()
            ? $booking->paymentIntents()->where('provider', SePaySandbox::PROVIDER)->whereIn('status', ['pending', 'review'])->latest('id')->first()
            : null;

        return view('customer.booking-show', ['booking' => $booking->load(['departure.tour', 'passengers', 'payment.attempts', 'payment.refunds', 'bill']), 'pendingQr' => $pendingQr, 'pendingSePay' => $pendingSePay]);
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

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->owned($booking);

        $data = $request->validate(
            ['cancel_reason' => ['required', 'string', 'max:255']],
            [
                'cancel_reason.required' => 'Vui lòng nhập lý do hủy tour.',
                'cancel_reason.max' => 'Lý do hủy không được quá 255 ký tự.',
            ]
        );

        $error = DB::transaction(function () use ($booking, $data) {
            TourDeparture::whereKey($booking->departure_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::with(['departure', 'payment'])->lockForUpdate()->findOrFail($booking->getKey());

            if (! $booking->canBeCancelled()) {
                return 'Đơn này không còn đủ điều kiện để hủy.';
            }

            $payment = $booking->payment;
            $percent = null;
            $refund = null;

            if ($payment && $payment->status === 'completed') {
                $percent = $booking->refundPercentNow();
                $refund = round($payment->amount * $percent / 100);
                app(RefundService::class)->request($booking, $refund, 'customer-cancel:'.$booking->getKey(), $data['cancel_reason']);
            }

            $departure = TourDeparture::lockForUpdate()->findOrFail($booking->departure_id);
            $departure->available_seats = min(
                $departure->max_seats,
                $departure->available_seats + $booking->total_adults + $booking->total_children
            );
            if ($departure->status === 'full') {
                $departure->status = 'open';
            }
            $departure->save();

            $booking->paymentIntents()->where('status', 'pending')->update(['status' => 'cancelled']);

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $data['cancel_reason'],
                'refund_percent' => $percent,
                'refund_amount' => $refund,
            ]);

            return null;
        });

        if ($error) {
            return back()->withErrors(['cancel' => $error]);
        }

        return back()->with('success', 'Đã hủy tour. Chỗ của bạn đã được trả lại cho chuyến đi.');
    }
}
