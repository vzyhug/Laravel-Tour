@extends('layouts.app')
@section('title', 'Giữ chỗ và đổi lịch')
@section('content')
<div class="container-page py-8 sm:py-12">
    <x-account-navigation/>
    <x-page-intro title="Sắp xếp lại ngày lên đường." eyebrow="Đặt chỗ · Quản lý lịch tour" icon="calendar" description="Theo dõi thời hạn giữ chỗ và xem các lựa chọn thay đổi lịch cho đơn đặt tour của bạn."/>
    <p class="mt-5 rounded-xl border border-[#e7dcd1] bg-white p-4 text-xs leading-6 text-muted">Đơn mới giữ chỗ trong {{ config('booking.hold_minutes') }} phút. Chỉ đổi lịch trước khi tạo giao dịch thanh toán.</p>
    <div class="mt-6 space-y-4">
        @forelse($bookings as $booking)
            <article class="card p-5 sm:p-7">
                <div class="flex flex-wrap items-center gap-3"><span class="text-[10px] font-semibold tracking-widest text-muted">{{ $booking->booking_code }}</span><x-booking-status :status="$booking->status" /></div>
                <h2 class="mt-3 font-serif text-2xl">{{ $booking->departure->tour->title }}</h2>
                <p class="my-4 text-sm text-muted">Khởi hành {{ $booking->departure->departure_date->format('d/m/Y') }} · <strong class="text-brand-600">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</strong></p>
                @if($booking->hold_expires_at && $booking->status === 'pending')
                    <p>Hạn thanh toán: {{ $booking->hold_expires_at->format('d/m/Y H:i') }}{{ $booking->holdHasExpired() ? ' — Đã hết hạn' : '' }}</p>
                @endif
                <a class="btn-secondary mt-4 min-h-10 text-xs" href="{{ route('customer.booking-changes.edit', $booking) }}">Xem và đổi lịch →</a>
            </article>
        @empty
            <div class="card p-12 text-center"><x-icon name="calendar" class="mx-auto size-10 text-brand-600"/><h2 class="mt-5 font-serif text-3xl">Một hành trình mới đang chờ.</h2><p class="mt-3 text-sm text-muted">Các đơn đặt tour của bạn sẽ xuất hiện tại đây.</p><a class="btn-primary mt-6" href="{{ route('tours.index') }}">Khám phá tour</a></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $bookings->links() }}</div>
</div>
@endsection
