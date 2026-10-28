@extends('layouts.app')
@section('title', 'Giữ chỗ và đổi lịch')
@section('content')
<div class="mx-auto max-w-5xl px-4 py-10">
    <h1 class="text-2xl font-bold">Giữ chỗ và đổi lịch tour</h1>
    <p class="mt-2 text-slate-600">Đơn mới giữ chỗ trong {{ config('booking.hold_minutes') }} phút. Chỉ đổi lịch trước khi tạo giao dịch thanh toán.</p>
    <div class="mt-6 space-y-4">
        @forelse($bookings as $booking)
            <article class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-bold">{{ $booking->booking_code }} — {{ $booking->departure->tour->title }}</h2>
                <p>Ngày đi: {{ $booking->departure->departure_date->format('d/m/Y') }} · {{ number_format($booking->total_amount, 0, ',', '.') }}₫</p>
                <p>Trạng thái: <x-booking-status :status="$booking->status" /></p>
                @if($booking->hold_expires_at && $booking->status === 'pending')
                    <p>Hạn thanh toán: {{ $booking->hold_expires_at->format('d/m/Y H:i') }}{{ $booking->holdHasExpired() ? ' — Đã hết hạn' : '' }}</p>
                @endif
                <a class="mt-3 inline-block font-semibold text-blue-700 underline" href="{{ route('customer.booking-changes.edit', $booking) }}">Xem và đổi lịch</a>
            </article>
        @empty
            <p>Bạn chưa có đơn đặt tour.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $bookings->links() }}</div>
</div>
@endsection
