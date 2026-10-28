@extends('layouts.app')
@section('title', 'Chọn phòng · '.$hotel->name)
@section('content')
<section class="container-page py-10 space-y-6">
<a class="text-link" href="{{ route('hotels.show', $hotel) }}">← {{ $hotel->name }}</a>
<h1 class="section-title">Chọn phòng lưu trú</h1>
<p>Thanh toán và hoàn tiền trong phiên bản này là mô phỏng, không thu tiền thật. Phòng được giữ 15 phút.</p>
<form class="grid gap-4 sm:grid-cols-4" method="GET">
<label>Nhận phòng<input class="w-full border p-3" type="date" name="check_in" min="{{ today()->toDateString() }}" required value="{{ $data['check_in'] ?? '' }}"></label>
<label>Trả phòng<input class="w-full border p-3" type="date" name="check_out" required value="{{ $data['check_out'] ?? '' }}"></label>
<label>Số phòng<input class="w-full border p-3" type="number" name="rooms" min="1" max="10" value="{{ $data['rooms'] }}" required></label>
<label>Số khách<input class="w-full border p-3" type="number" name="guests" min="1" max="100" value="{{ $data['guests'] }}" required></label>
<button class="btn-primary">Tìm phòng trống</button>
</form>
@if(!empty($data['check_in']))
@forelse($offers as $type)
<article class="rounded-xl border p-6 space-y-3"><h2 class="text-xl">{{ $type->name }} @if($type->is_demo) — DỮ LIỆU DEMO @endif</h2>
<p>Tối đa {{ $type->max_guests }} khách/phòng · Tổng {{ number_format($type->inventory->sum('nightly_price') * $data['rooms']) }} ₫</p>
@if($type->is_demo)<p>Giá và tồn phòng minh họa, không phải thông tin bán phòng của khách sạn.</p>@endif
@foreach($type->inventory as $night)<p>{{ $night->stay_date->format('d/m/Y') }}: {{ number_format($night->nightly_price) }} ₫/phòng/đêm</p>@endforeach
@if(session('customer_id'))
<form method="POST" action="{{ route('hotel-bookings.store') }}">@csrf
<input type="hidden" name="request_key" value="{{ $requestKey }}"><input type="hidden" name="room_type_id" value="{{ $type->id }}">
@foreach(['check_in', 'check_out', 'rooms', 'guests'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $data[$field] }}">@endforeach
<button class="btn-primary">Giữ phòng 15 phút</button></form>
@else<a class="text-link" href="{{ route('login') }}">Đăng nhập để đặt phòng</a>@endif
</article>
@empty<p>Chưa có phòng phù hợp hoặc chưa có giá và tồn cho toàn bộ ngày đã chọn.</p>@endforelse
@endif
@if(session('customer_id'))<a class="text-link" href="{{ route('hotel-bookings.index') }}">Đơn khách sạn của tôi</a>@endif
</section>
@endsection
