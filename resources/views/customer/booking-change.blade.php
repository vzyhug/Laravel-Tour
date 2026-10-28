@extends('layouts.app')
@section('title', 'Đổi lịch tour')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-10">
    <a class="text-blue-700 underline" href="{{ route('customer.booking-changes.index') }}">Danh sách đơn</a>
    <h1 class="mt-4 text-2xl font-bold">Đổi lịch — {{ $booking->booking_code }}</h1>
    <p class="mt-3">{{ $booking->departure->tour->title }} · {{ $booking->departure->departure_date->format('d/m/Y') }}</p>
    <p>Tổng hiện tại: {{ number_format($booking->total_amount, 0, ',', '.') }}₫</p>
    @if($booking->hold_expires_at)
        <p>Hạn giữ chỗ: {{ $booking->hold_expires_at->format('d/m/Y H:i') }}</p>
    @endif
    @if(session('success'))<p role="status" class="mt-4 rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="mt-4 rounded bg-red-50 p-3 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($booking->status === 'pending' && !$booking->holdHasExpired() && !$booking->payment && !$booking->consultation_id && !$booking->departure->is_private)
        <form method="POST" action="{{ route('customer.booking-changes.update', $booking) }}" class="mt-6 space-y-4 rounded-xl border bg-white p-5">
            @csrf
            <input type="hidden" name="expected_departure_id" value="{{ $booking->departure_id }}">
            <label class="block">Lịch khởi hành mới
                <select name="departure_id" required class="mt-1 block w-full rounded border p-3">
                    <option value="">Chọn lịch mới</option>
                    @foreach($departures as $departure)
                        <option value="{{ $departure->getKey() }}" @selected(old('departure_id') == $departure->getKey())>{{ $departure->tour->title }} — {{ $departure->departure_date->format('d/m/Y') }} — {{ number_format($departure->adult_price, 0, ',', '.') }}₫/khách</option>
                    @endforeach
                </select>
            </label>
            <label class="block">Lý do đổi<textarea name="reason" required maxlength="255" class="mt-1 block w-full rounded border p-3">{{ old('reason') }}</textarea></label>
            <p class="text-sm text-slate-600">Tổng tiền được tính lại theo giá lịch mới và mã giảm giá còn hợp lệ. Điểm đón chuyển về điểm tập trung của tour mới. Hạn giữ chỗ không được gia hạn.</p>
            <label class="flex gap-2"><input type="checkbox" name="accept_price" value="1" required> Tôi đồng ý tính lại giá và đổi điểm đón theo lịch mới.</label>
            <button class="rounded bg-blue-700 px-5 py-3 font-semibold text-white">Xác nhận đổi lịch</button>
        </form>
    @else
        <p role="status" class="mt-5 rounded bg-amber-50 p-4">Đơn này không đủ điều kiện đổi trực tuyến. Chỉ hỗ trợ tour thông thường còn hạn giữ chỗ và chưa tạo giao dịch thanh toán. Vui lòng liên hệ hỗ trợ cho các trường hợp khác.</p>
    @endif
    <a class="mt-5 inline-block text-blue-700 underline" href="{{ route('customer.bookings.show', $booking) }}">Chi tiết đơn và thanh toán</a>
    <h2 class="mt-8 text-lg font-bold">Lịch sử đổi</h2>
    @forelse($booking->changes as $change)
        <div class="mt-3 rounded border p-4">
            <p>{{ $change->created_at->format('d/m/Y H:i') }} · {{ $change->fromDeparture->tour->title }} → {{ $change->toDeparture->tour->title }}</p>
            <p>{{ $change->fromDeparture->departure_date->format('d/m/Y') }} → {{ $change->toDeparture->departure_date->format('d/m/Y') }}</p>
            <p>{{ number_format($change->previous_amount, 0, ',', '.') }}₫ → {{ number_format($change->new_amount, 0, ',', '.') }}₫ · {{ $change->reason }}</p>
        </div>
    @empty
        <p class="mt-2 text-slate-600">Chưa có lần đổi lịch nào.</p>
    @endforelse
</div>
@endsection
