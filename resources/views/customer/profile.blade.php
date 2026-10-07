@extends('layouts.app')
@section('title', 'Hồ sơ cá nhân · VietGo')
@section('content')
<section class="container-page py-12"><div class="mx-auto max-w-2xl"><h1 class="text-3xl font-black text-ink">Hồ sơ cá nhân</h1><form method="POST" action="{{ route('customer.profile.update') }}" class="card mt-7 grid gap-5 p-7">@csrf @method('PUT')<label><span class="label">Họ và tên</span><input class="field" name="full_name" value="{{ old('full_name', $customer->full_name) }}" required></label><label><span class="label">Email</span><input class="field" type="email" name="email" value="{{ old('email', $customer->email) }}" required></label><label><span class="label">Số điện thoại</span><input class="field" name="phone" value="{{ old('phone', $customer->phone) }}" required></label><label><span class="label">Địa chỉ</span><input class="field" name="address" value="{{ old('address', $customer->address) }}"></label><button class="btn-primary">Lưu thay đổi</button></form></div></section>
@endsection
