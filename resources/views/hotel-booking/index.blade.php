@extends('layouts.app')
@section('title', 'Nơi nghỉ của tôi · VietGo')
@section('content')
<section class="container-page py-8 sm:py-12">
    <x-account-navigation/>
    <x-page-intro title="Những nơi dừng chân." eyebrow="Lưu trú · Hành trình của bạn" icon="bed" description="Theo dõi lịch nhận phòng, thông tin đặt chỗ và chuẩn bị cho kỳ nghỉ sắp tới."><a href="{{ route('hotels.index') }}" class="text-link mt-4">Tìm nơi nghỉ tiếp theo →</a></x-page-intro>
    <div class="mt-8 grid gap-4">
        @forelse($bookings as $booking)
            @php($status = ['pending' => 'Chờ thanh toán', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn giữ phòng'][$booking->status] ?? $booking->status)
            <article class="card grid gap-5 p-5 sm:grid-cols-[1fr_auto] sm:items-center sm:p-7">
                <div><div class="flex flex-wrap items-center gap-3"><span class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ $booking->code }}</span><span class="badge">{{ $status }}</span></div><h2 class="mt-3 font-serif text-2xl"><a href="{{ route('hotel-bookings.show', $booking) }}" class="hover:text-brand-600">{{ $booking->roomType->hotel->name }}</a></h2><p class="mt-2 text-xs text-muted">{{ $booking->roomType->name }} · {{ $booking->rooms }} phòng · {{ $booking->guests }} khách</p><div class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sand px-3 py-2 text-xs"><x-icon name="calendar" class="size-4 text-brand-600"/>{{ $booking->check_in->format('d/m/Y') }} <span class="text-muted">→</span> {{ $booking->check_out->format('d/m/Y') }}</div></div>
                <div class="flex flex-wrap items-center justify-between gap-4 sm:block sm:text-right"><div><p class="text-[10px] text-muted">Tổng đặt phòng</p><p class="mt-1 font-serif text-2xl text-brand-600">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</p></div><a class="btn-secondary mt-3 min-h-10 px-4 text-xs" href="{{ route('hotel-bookings.show', $booking) }}">Chi tiết đặt phòng →</a></div>
            </article>
        @empty
            <div class="card px-6 py-16 text-center"><span class="mx-auto grid size-16 place-items-center rounded-2xl bg-linen text-brand-600"><x-icon name="bed" class="size-7"/></span><h2 class="mt-5 font-serif text-3xl">Nơi nghỉ đầu tiên đang chờ bạn.</h2><p class="mx-auto mt-3 max-w-md text-sm leading-7 text-muted">Tìm một khách sạn hợp ý tại điểm đến yêu thích, rồi bắt đầu lên lịch cho chuyến đi.</p><a href="{{ route('destinations.index') }}" class="btn-primary mt-6">Chọn điểm đến</a></div>
        @endforelse
    </div>
    <div class="mt-8">{{ $bookings->links() }}</div>
</section>
@endsection
