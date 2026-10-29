@extends('layouts.app')
@section('title', 'Đặt phòng '.$booking->code.' · VietGo')
@section('content')
@php
    $expired = $booking->status === 'pending' && ($booking->expires_at->lte(now()) || $booking->check_in->lt(today()));
    $status = $expired ? 'Hết hạn giữ phòng' : (['pending' => 'Chờ thanh toán', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn giữ phòng'][$booking->status] ?? $booking->status);
@endphp
<section class="container-page py-8 sm:py-12">
    <a class="text-link mb-5" href="{{ route('hotel-bookings.index') }}">← Đơn lưu trú của tôi</a>
    <x-page-intro :title="$booking->roomType->hotel->name" :eyebrow="'Đặt phòng · '.$booking->code" icon="bed" :description="$booking->roomType->name"><span class="badge mt-5">{{ $status }}</span></x-page-intro>
    <div class="mt-7 grid items-start gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            <section class="card p-6 sm:p-8"><h2 class="font-serif text-2xl">Những ngày nghỉ của bạn</h2><div class="mt-5 grid grid-cols-2 gap-4 rounded-xl bg-sand p-5"><div><p class="eyebrow text-[9px]">Nhận phòng</p><p class="mt-2 font-serif text-2xl">{{ $booking->check_in->format('d/m/Y') }}</p></div><div class="border-l border-[#e7dcd1] pl-5"><p class="eyebrow text-[9px]">Trả phòng</p><p class="mt-2 font-serif text-2xl">{{ $booking->check_out->format('d/m/Y') }}</p></div></div><div class="mt-5 flex flex-wrap gap-3 text-xs text-muted"><span class="rounded-full bg-linen px-4 py-2">{{ $booking->rooms }} phòng</span><span class="rounded-full bg-linen px-4 py-2">{{ $booking->guests }} khách</span><span class="rounded-full bg-linen px-4 py-2">{{ $booking->roomType->name }}</span></div></section>
            <section class="card p-6 sm:p-8"><h2 class="font-serif text-2xl">Chi tiết giá theo đêm</h2><div class="mt-5 divide-y divide-[#eee5dd]">@foreach($booking->nights as $night)<div class="flex flex-wrap justify-between gap-2 py-3 text-xs"><span class="text-muted">{{ $night->stay_date->format('d/m/Y') }} · {{ $night->rooms }} phòng</span><strong>{{ number_format($night->nightly_price, 0, ',', '.') }}₫ / phòng</strong></div>@endforeach</div><p class="mt-4 text-xs leading-6 text-muted">Giá theo đêm được lưu tại thời điểm đặt phòng.</p></section>
            <section class="rounded-2xl border border-[#e7dcd1] bg-linen/50 p-6"><h2 class="font-serif text-xl">Điều kiện đặt phòng</h2><p class="mt-3 whitespace-pre-line text-xs leading-7 text-muted">{{ $booking->cancellation_policy }}</p></section>
        </div>
        <aside class="card p-6 sm:p-8 lg:sticky lg:top-28"><p class="eyebrow">Tổng đặt phòng</p><p class="mt-3 font-serif text-4xl text-brand-600">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</p><p class="mt-3 text-xs leading-6 text-muted">{{ $booking->rooms }} phòng · {{ $booking->guests }} khách</p>
            @if($booking->payment)<div class="mt-5 rounded-xl bg-sand p-4 text-xs"><span class="text-muted">Thanh toán</span><p class="mt-2 font-semibold">{{ ['completed' => 'Đã thanh toán', 'refunded' => 'Đã hoàn tiền', 'pending' => 'Đang xử lý', 'failed' => 'Thất bại'][$booking->payment->status] ?? $booking->payment->status }}</p></div>@endif
            @if($booking->status === 'pending')<p class="mt-5 rounded-xl bg-brand-50 p-4 text-xs leading-6 text-brand-700">{{ $expired ? 'Thời gian giữ phòng đã hết.' : 'Vui lòng thanh toán trước' }} @unless($expired)<strong>{{ $booking->expires_at->timezone('Asia/Ho_Chi_Minh')->format('H:i, d/m/Y') }}</strong> (giờ Việt Nam).@endunless</p>@endif
            @if(\App\Services\Payments\DemoQrPayment::enabled() && $booking->status === 'pending' && $booking->expires_at->isFuture() && $booking->check_in->gte(today()))<form class="mt-5" method="POST" action="{{ route('hotel-bookings.pay', $booking) }}">@csrf<button class="btn-primary w-full">Xác nhận thanh toán</button></form>@endif
            <a class="text-link mt-5" href="{{ route('hotels.show', $booking->roomType->hotel) }}">Xem thông tin khách sạn →</a>
            @if(in_array($booking->status, ['pending', 'confirmed']) && $booking->check_in->isFuture())<form class="mt-5 border-t border-[#eee5dd] pt-5" method="POST" action="{{ route('hotel-bookings.cancel', $booking) }}">@csrf<button class="btn-secondary min-h-10 w-full text-xs">Hủy đơn đặt phòng</button></form>@endif
        </aside>
    </div>
</section>
@endsection
