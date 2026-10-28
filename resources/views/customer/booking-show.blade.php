@extends('layouts.app')

@section('title', 'Mã đặt tour: '.$booking->booking_code.' · VietGo')

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
                <p class="text-sm text-slate-500">Mã đặt tour: <span class="font-semibold text-ink">{{ $booking->booking_code }}</span></p>
                <h1 class="text-3xl font-black text-ink">{{ $departure->tour->title }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="badge h-fit">{{ $labels[$booking->status] ?? $booking->status }}</span>
                @if($booking->bill)
                    <a class="btn-secondary" href="{{ route('customer.bookings.invoice', $booking) }}">Xem hóa đơn</a>
                @endif
            </div>
        </div>

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="card p-6">
                <h2 class="text-lg font-bold">Thông tin chuyến đi</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between">
                        <dt>Khởi hành</dt>
                        <dd class="font-semibold">
                            {{ $departure->departure_date->format('d/m/Y') }}@if($departure->time_label) · {{ $departure->time_label }}@endif
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
                                Tự đến: {{ $departure->tour->pickup_point ?: 'điểm tập trung sẽ thông báo' }}
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

                @if(str_starts_with($booking->payment?->transaction_code ?? '', 'SEPAY-SBX-'))<p class="mt-3 text-xs text-muted">SePay Sandbox · Giao dịch thử nghiệm, không chuyển tiền thật.</p>@endif
                @if($booking->payment)
                    <p class="mt-5 break-all text-xs leading-7 text-muted">Mã giao dịch: <strong class="text-ink">{{ $booking->payment->transaction_code }}</strong></p>
                    @if($booking->payment->payment_method === 'bank_transfer' && str_starts_with($booking->payment->transaction_code ?? '', 'DEMO-'))<p class="mt-1 text-xs text-muted">Chuyển khoản VietQR · Demo</p>@endif
                @endif
                @if(!$booking->payment && in_array($booking->status, ['pending', 'confirmed'], true))
                    @if($pendingSePay)
                    <a class="btn-primary mt-5 w-full" href="{{ route('payments.sepay.show', [$booking, $pendingSePay]) }}">Xem thanh toán SePay Sandbox</a>
                    @elseif($pendingQr)
                    <a class="btn-primary mt-5 w-full" href="{{ route('payments.qr.show', [$booking, $pendingQr]) }}">Tiếp tục thanh toán QR</a>
                    @else
                    <a class="btn-primary mt-5 w-full" href="{{ route('payments.create', $booking) }}">Thanh toán ngay</a>
                    @endif
                @endif
            </div>

            <div class="card p-6">
                <h2 class="text-lg font-bold">Hành khách</h2>
                <ul class="mt-4 grid gap-2 text-sm">
                    @foreach($booking->passengers as $passenger)
                        <li class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <span>{{ $passenger->full_name }}</span>
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
                        Hủy trong {{ config('booking.full_refund_hours') }} giờ sau khi đặt được hoàn 100%, sau đó hoàn {{ config('booking.partial_refund_percent') }}%.
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
