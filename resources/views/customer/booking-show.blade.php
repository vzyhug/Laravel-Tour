@extends('layouts.app')

@section('title', $booking->booking_code . ' · VietGo')

@php
    $labels = ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'];
    $departure = $booking->departure;
@endphp

@section('content')
<section class="container-page py-12">
    <div class="mx-auto max-w-4xl">
        <a class="font-semibold text-brand-700" href="{{ route('customer.dashboard') }}">← Trở lại</a>

        <div class="mt-5 flex flex-wrap justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">{{ $booking->booking_code }}</p>
                <h1 class="text-3xl font-black text-ink">{{ $booking->serviceValue('title', $departure->tour->title) }}</h1>
            </div>
            <div class="flex items-center gap-3">
                @if(in_array($booking->status, ['confirmed', 'completed']) && $booking->payment?->status === 'completed')
                    <a class="btn-primary" href="{{ route('customer.bookings.voucher', $booking) }}">Chứng từ tour demo</a>
                @endif
                <span class="badge h-fit">{{ $labels[$booking->status] ?? $booking->status }}</span>
                @if($booking->bill)
                    <a class="btn-secondary" href="{{ route('customer.bookings.invoice', $booking) }}">Xem hóa đơn</a>
                @endif
            </div>
        </div>

        <x-booking-progress :booking="$booking" />
        @if($booking->events->isNotEmpty())
            <details class="card my-6 p-5"><summary class="cursor-pointer text-sm font-semibold">Lịch sử xử lý đơn</summary><ol class="mt-4 grid gap-3 text-sm">@foreach($booking->events as $event)<li><time class="mr-3 text-xs text-muted">{{ $event->occurred_at->copy()->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }} (UTC+7)</time>{{ ['held' => 'Đã giữ chỗ', 'hold_expired' => 'Hết hạn; đã giải phóng chỗ', 'payment_failed_demo' => 'Thanh toán mô phỏng thất bại', 'payment_captured_demo' => 'Đã nhận thanh toán mô phỏng', 'voucher_available_demo' => 'Chứng từ demo sẵn sàng', 'cancelled' => 'Đã hủy tour', 'refund_succeeded_demo' => 'Đã hoàn tiền mô phỏng', 'admin_status_changed' => 'Nhân viên cập nhật trạng thái'][$event->type] ?? 'Đã cập nhật đơn' }}</li>@endforeach</ol></details>
        @endif
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="card p-6">
                <h2 class="text-lg font-bold">Thông tin chuyến đi</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between">
                        <dt>Khởi hành</dt>
                        <dd class="font-semibold">
                            {{ \Carbon\Carbon::parse($booking->serviceValue('departure_date', $departure->departure_date))->format('d/m/Y') }}@if($booking->serviceValue('departure_time', $departure->time_label)) · {{ $booking->serviceValue('departure_time', $departure->time_label) }}@endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Hành khách</dt>
                        <dd class="font-semibold">{{ $booking->passengers->count() }} người</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt>Cách tham gia</dt>
                        <dd class="text-right font-semibold">
                            @if($booking->pickup_type === 'door')
                                Đón tại: {{ $booking->pickup_address }}
                                @if($booking->pickup_note)
                                    <span class="block text-xs font-normal text-slate-500">{{ $booking->pickup_note }}</span>
                                @endif
                            @else
                                Tự đến: {{ $booking->serviceValue('pickup_point', $departure->tour->pickup_point) ?: 'điểm tập trung sẽ thông báo' }}
                            @endif
                        </dd>
                    </div>
                    @if($booking->discount_amount > 0)
                        <div class="flex justify-between">
                            <dt>Mã giảm giá</dt>
                            <dd class="font-semibold">{{ $booking->coupon?->code }} (-{{ number_format($booking->discount_amount, 0, ',', '.') }}₫)</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt>Tổng tiền</dt>
                        <dd class="font-bold text-orange-600">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</dd>
                    </div>
                </dl>

                @if(!$booking->payment && in_array($booking->status, ['pending', 'confirmed'], true))
                    <a class="btn-primary mt-5 w-full" href="{{ route('payments.create', $booking) }}">Thanh toán ngay</a>
                @endif
            </div>

            <div class="card p-6">
                <h2 class="text-lg font-bold">Hành khách</h2>
                <ul class="mt-4 grid gap-2 text-sm">
                    @foreach($booking->passengers as $passenger)
                        <li class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <span>{{ $passenger->full_name }}</span>
                            <span class="text-slate-500">{{ $passenger->passenger_type === 'adult' ? 'Người lớn' : 'Trẻ em' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Hủy tour --}}
        @if($booking->status === 'cancelled')
            <div class="card mt-6 p-6">
                <h2 class="text-lg font-bold">Thông tin hủy tour</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between">
                        <dt>Hủy lúc</dt>
                        <dd class="font-semibold">{{ $booking->cancelled_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt>Lý do</dt>
                        <dd class="text-right font-semibold">{{ $booking->cancel_reason }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Hoàn tiền</dt>
                        <dd class="font-semibold">
                            @if($booking->refund_amount !== null)
                                {{ $booking->refund_percent }}% · {{ number_format($booking->refund_amount, 0, ',', '.') }}₫
                            @else
                                Không có (đơn chưa thanh toán)
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        @elseif(in_array($booking->status, ['pending', 'confirmed']))
            <div class="card mt-6 p-6">
                <h2 class="text-lg font-bold">Hủy tour</h2>

                @error('cancel')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                @enderror

                @if($booking->canBeCancelled())
                    <p class="mt-3 text-sm text-slate-600">
                        Bạn có thể hủy đến hết ngày <strong>{{ $booking->cancelDeadline()->format('d/m/Y') }}</strong>.
                        @if($booking->payment && $booking->payment->status === 'completed')
                            Nếu hủy ngay bây giờ, bạn được hoàn
                            <strong>{{ $booking->refundPercentNow() }}%</strong>
                            (khoảng {{ number_format($booking->payment->amount * $booking->refundPercentNow() / 100, 0, ',', '.') }}₫).
                        @else
                            Đơn chưa thanh toán nên không có khoản hoàn tiền.
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Hủy trong {{ $booking->policyValue('full_refund_hours') }} giờ sau khi đặt được hoàn 100%, sau đó hoàn {{ $booking->policyValue('partial_refund_percent') }}%.
                    </p>

                    <form class="mt-4 grid gap-4" method="POST" action="{{ route('customer.bookings.cancel', $booking) }}"
                          onsubmit="return confirm('Bạn chắc chắn muốn hủy tour này? Thao tác không thể hoàn tác.')">
                        @csrf
                        <label>
                            <span class="label">Lý do hủy</span>
                            <textarea class="field" name="cancel_reason" rows="3" required>{{ old('cancel_reason') }}</textarea>
                        </label>
                        @error('cancel_reason')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <div>
                            <button class="rounded-xl border border-red-300 px-5 py-3 font-semibold text-red-600 hover:bg-red-50">
                                Hủy tour
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-600">
                        Đơn này đã quá hạn hủy (hạn hủy là hết ngày {{ $booking->cancelDeadline()->format('d/m/Y') }}).
                        Nếu cần hỗ trợ, vui lòng liên hệ VietGo.
                    </p>
                @endif
            </div>
        @endif

        @if($booking->status === 'completed' || ($booking->status === 'confirmed' && $booking->departure->return_date->isPast()))
            <form class="card mt-6 p-6" method="POST" action="{{ route('customer.bookings.review', $booking) }}">
                @csrf
                <h2 class="text-lg font-bold">Đánh giá chuyến đi</h2>
                <div class="mt-4 grid gap-4">
                    <label>
                        <span class="label">Số sao</span>
                        <select class="field" name="rating" required>
                            @foreach(range(5, 1) as $rating)
                                <option value="{{ $rating }}">{{ $rating }} sao</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="label">Chia sẻ trải nghiệm</span>
                        <textarea class="field" name="feedback" rows="4" minlength="5" maxlength="2000" required>{{ old('feedback') }}</textarea>
                        @error('feedback')<p class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>
                    <button class="btn-primary">Gửi đánh giá</button>
                </div>
            </form>
        @endif
    </div>
</section>
@endsection
