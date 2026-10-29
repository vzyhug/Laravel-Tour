@extends('layouts.app')
@section('content')
<section class="container-page py-10 space-y-5">
<a class="text-link" href="{{ route('hotel-bookings.index') }}">← Đơn khách sạn</a>
<h1 class="section-title">{{ $booking->code }}</h1>
<p>{{ $booking->roomType->hotel->name }} · {{ $booking->roomType->name }} </p>
<p>{{ $booking->check_in->format('d/m/Y') }} → {{ $booking->check_out->format('d/m/Y') }} · {{ $booking->rooms }} phòng · {{ $booking->guests }} khách</p>
<p>Trạng thái: {{ $booking->status }} @if($booking->status === 'pending' && ($booking->expires_at->lte(now()) || $booking->check_in->lt(today()))) — Đã hết hạn giữ chỗ @endif</p>
<p>Hạn giữ: {{ $booking->expires_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }} (giờ Việt Nam)</p>
@foreach($booking->nights as $night)<p>{{ $night->stay_date->format('d/m/Y') }}: {{ number_format($night->nightly_price) }} ₫ × {{ $night->rooms }} phòng</p>@endforeach
<p class="text-xl">Tổng: {{ number_format($booking->total_amount) }} ₫</p>
<p>{{ str_replace('tiền mô phỏng', 'tiền', $booking->cancellation_policy) }}</p>
@if($booking->payment)<p>Thanh toán: {{ $booking->payment->status }}</p>@endif
@if(\App\Services\Payments\DemoQrPayment::enabled() && $booking->status === 'pending' && $booking->expires_at->isFuture() && $booking->check_in->gte(today()))<form method="POST" action="{{ route('hotel-bookings.pay', $booking) }}">@csrf<button class="btn-primary">Xác nhận thanh toán</button></form>@endif
@if(in_array($booking->status, ['pending', 'confirmed']) && $booking->check_in->isFuture())<form method="POST" action="{{ route('hotel-bookings.cancel', $booking) }}">@csrf<button class="btn-primary">Hủy đơn</button></form>@endif
</section>
@endsection
