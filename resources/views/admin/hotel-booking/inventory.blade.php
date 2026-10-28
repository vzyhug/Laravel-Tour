@extends('layouts.app')
@section('content')
<section class="container-page py-10 space-y-6"><a class="text-link" href="{{ route('admin.hotel-booking.index') }}">← Quản trị khách sạn</a><h1 class="section-title">{{ $type->hotel->name }} · {{ $type->name }}</h1>
@if($type->is_demo)<p>DỮ LIỆU DEMO — giá và tồn phòng minh họa.</p>@endif
<p>Nhập theo từng đêm; ngày trả phòng không chiếm tồn. Ngày chưa nhập không mở bán.</p>
<form method="POST" action="{{ route('admin.hotel-booking.inventory.save', $type) }}" class="grid gap-3 sm:grid-cols-3">@csrf @method('PUT')
<label>Đêm lưu trú<input class="w-full border p-3" type="date" name="stay_date" min="{{ today()->toDateString() }}" required></label>
<label>Tổng phòng<input class="w-full border p-3" type="number" name="total_rooms" min="0" max="10000" required></label>
<label>Giá VND/phòng/đêm<input class="w-full border p-3" type="number" name="nightly_price" min="1" max="1000000000" required></label>
<button class="btn-primary">Lưu tồn và giá</button></form>
@foreach($nights as $night)<p>{{ $night->stay_date->format('d/m/Y') }} · Trống {{ $night->available_rooms }}/{{ $night->total_rooms }} · {{ number_format($night->nightly_price) }} ₫</p>@endforeach
{{ $nights->links() }}</section>
@endsection
