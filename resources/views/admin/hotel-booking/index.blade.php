@extends('layouts.app')
@section('content')
<section class="container-page py-10 space-y-8"><h1 class="section-title">Quản trị đặt khách sạn</h1>
<p>Thanh toán và hoàn tiền khách sạn hiện chỉ mô phỏng.</p>
<h2 class="text-2xl">Thêm loại phòng</h2>
<form method="POST" action="{{ route('admin.hotel-booking.room-types.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
<label>Khách sạn<select class="w-full border p-3" name="hotel_id">@foreach($hotels as $hotel)<option value="{{ $hotel->id }}">{{ $hotel->name }}</option>@endforeach</select></label>
<label>Tên loại phòng<input class="w-full border p-3" name="name" maxlength="120" required></label>
<label>Sức chứa/phòng<input class="w-full border p-3" type="number" name="max_guests" min="1" max="20" required></label>
<label><input type="checkbox" name="is_demo" value="1"> Dữ liệu demo (giá và tồn minh họa)</label><button class="btn-primary">Thêm</button></form>
<h2 class="text-2xl">Tồn và giá</h2>
@foreach($types as $type)<p><a class="text-link" href="{{ route('admin.hotel-booking.inventory', $type) }}">{{ $type->hotel->name }} · {{ $type->name }}</a> @if($type->is_demo) · DEMO @endif</p>@endforeach
{{ $types->links() }}
<h2 class="text-2xl">Đơn lưu trú</h2>
@forelse($bookings as $booking)<article class="border p-5"><p>{{ $booking->code }} · {{ $booking->customer->full_name }} · {{ $booking->roomType->hotel->name }} · {{ $booking->status }}</p><p>{{ $booking->check_in->format('d/m/Y') }} → {{ $booking->check_out->format('d/m/Y') }} · {{ $booking->rooms }} phòng · {{ number_format($booking->total_amount) }} ₫</p>
@if($booking->payment)<p>Thanh toán mô phỏng: {{ $booking->payment->status }}</p>@endif
@if($booking->status === 'confirmed' && !$booking->check_out->isFuture())<form method="POST" action="{{ route('admin.hotel-booking.complete', $booking) }}">@csrf<button class="btn-primary">Hoàn thành lưu trú</button></form>@endif
</article>@empty<p>Chưa có đơn lưu trú.</p>@endforelse
{{ $bookings->links() }}</section>
@endsection
