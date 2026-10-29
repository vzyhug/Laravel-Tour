@extends('layouts.app')

@section('title', 'Mã đặt tour: '.$booking->booking_code.' · VietGo')

@php
    $labels = ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'];
    $departure = $booking->departure;
    $quote = $booking->quote_snapshot ?? [];
@endphp

@section('content')
<section class="container-page py-12">
    <div class="mx-auto max-w-4xl">
        <a class="font-semibold text-brand-700" href="{{ route('customer.dashboard') }}">← Trở lại</a>

        <div class="mt-5 flex flex-wrap justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Mã đặt tour: <span class="font-semibold text-ink">{{ $booking->booking_code }}</span></p>
                <h1 class="text-3xl font-black text-ink">{{ $quote['tour'] ?? $departure->tour->title }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="badge h-fit">{{ $labels[$booking->status] ?? $booking->status }}</span>
                @if($booking->bill)
                    <a class="btn-secondary" href="{{ route('customer.bookings.invoice', $booking) }}">Xem hóa đơn</a>
                @endif
            </div>
        </div>

        @include('itineraries.accepted-quote', ['booking' => $booking])

        @if($booking->status === 'pending' && $booking->hold_expires_at)
            <p class="mt-5 text-sm text-slate-600">Giữ chỗ đến {{ $booking->hold_expires_at->format('d/m/Y H:i') }}. Đơn chưa thanh toán sẽ tự hủy khi hết hạn.</p>
        @endif

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="card p-6">
                <h2 class="text-lg font-bold">Thông tin chuyến đi</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between">
                        <dt>Khởi hành</dt>
                        <dd class="font-semibold">
                            {{ isset($quote['travel_date']) ? \Carbon\Carbon::parse($quote['travel_date'])->format('d/m/Y') : $departure->departure_date->format('d/m/Y') }}@if($departure->time_label) · {{ $departure->time_label }}@endif
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
                                Tự đến: {{ (array_key_exists('pickup_point', $quote) ? $quote['pickup_point'] : $departure->tour->pickup_point) ?: 'điểm tập trung sẽ thông báo' }}
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
                    @if($bankPromotion)
                        <div class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                            <div class="flex justify-between gap-3">
                                <span>Ưu đãi ngân hàng/VNPay{{ $bankPromotion['code'] ? ' · '.$bankPromotion['code'] : '' }}</span>
                                <strong>−{{ number_format($bankPromotion['amount'], 0, ',', '.') }}₫</strong>
                            </div>
                            <div class="mt-1 flex justify-between gap-3">
                                <span>Số tiền sau ưu đãi thanh toán</span>
                                <strong>{{ number_format($bankPromotion['paid_amount'], 0, ',', '.') }}₫</strong>
                            </div>
                        </div>
                    @endif
                </dl>

                @if($booking->payment)
                    <p class="mt-3 text-sm">Trạng thái thanh toán: {{ $booking->payment->status_label }}</p>
                    @if(str_starts_with($booking->payment->transaction_code ?? '', 'DEMO-'))
                        <p class="mt-3 text-xs leading-7 text-muted">Tham chiếu thanh toán: <strong class="text-ink">{{ $booking->booking_code }}</strong></p>
                    @else
                    <p class="mt-3 break-all text-xs leading-7 text-muted">{{ str_starts_with($booking->payment->transaction_code ?? '', 'SEPAY-SBX-ORDER-') ? 'Mã đơn thanh toán SePay' : 'Mã giao dịch' }}: <strong class="text-ink">{{ $booking->payment->transaction_code ?: 'Chưa có giao dịch hoàn tất' }}</strong></p>
                    @endif
                    @if($booking->payment->payment_method === 'bank_transfer' && str_starts_with($booking->payment->transaction_code ?? '', 'DEMO-'))<p class="mt-1 text-xs text-muted">Chuyển khoản VietQR</p>@endif
                @endif
                @if($pendingSePay && $pendingSePay->status === 'review')
                    <p class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">Giao dịch SePay đang cần nhân viên kiểm tra trước khi có thể thanh toán lại.</p>
                @elseif((!$booking->payment || in_array($booking->payment->status, ['pending', 'failed'], true)) && in_array($booking->status, ['pending', 'confirmed'], true) && !$booking->holdHasExpired())
                    @if((!$booking->payment || $booking->payment->status === 'failed') && $pendingSePay)
                        <a class="btn-primary mt-5 w-full" href="{{ route('payments.sepay.show', [$booking, $pendingSePay]) }}">Xem thanh toán SePay</a>
                    @elseif((!$booking->payment || $booking->payment->status === 'failed') && $pendingQr)
                        <a class="btn-primary mt-5 w-full" href="{{ route('payments.qr.show', [$booking, $pendingQr]) }}">Xem mã VietQR</a>
                    @else
                        <a class="btn-primary mt-5 w-full" href="{{ route('payments.create', $booking) }}">Thanh toán ngay</a>
                    @endif
                @endif
                @if($booking->status === 'pending' && !$booking->payment && !$booking->consultation_id && !$departure->is_private && !$booking->holdHasExpired())
                    <a class="btn-secondary mt-3 w-full" href="{{ route('customer.booking-changes.edit', $booking) }}">Đổi ngày hoặc đổi tour</a>
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

        @if($booking->payment && $booking->payment->refunds->isNotEmpty())
            <section class="card mt-6 p-6" aria-label="Trạng thái hoàn tiền">
                <h2 class="text-lg font-bold">Trạng thái hoàn tiền</h2>
                <ul class="mt-4 grid gap-3 text-sm">
                    @foreach($booking->payment->refunds as $refund)
                        <li class="flex flex-wrap justify-between gap-3">
                            <span>{{ number_format($refund->amount, 0, ',', '.') }}₫</span>
                            <strong>{{ ['requested' => 'Đã yêu cầu', 'processing' => 'Đang xử lý', 'succeeded' => 'Hoàn tiền thành công', 'failed' => 'Hoàn tiền chưa thành công — đang chờ xử lý lại'][$refund->status] ?? $refund->status }}</strong>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

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
                            <textarea class="field" name="cancel_reason" rows="3" maxlength="255" required>{{ \App\Support\CustomerValidation::text(old('cancel_reason')) }}</textarea>
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

        @if($booking->canBeReviewed())
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
                        <textarea class="field" name="feedback" rows="4" minlength="5" maxlength="2000" required>{{ \App\Support\CustomerValidation::text(old('feedback')) }}</textarea>
                        @error('feedback')<p class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>
                    <button class="btn-primary">Gửi đánh giá</button>
                </div>
            </form>
        @endif
    </div>
</section>
@endsection
