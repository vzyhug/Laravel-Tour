@extends('layouts.app')
@section('title', 'Đăng ký · VietGo')
@section('content')
<section class="container-page grid place-items-center py-12"><div class="card w-full max-w-2xl p-7 sm:p-9"><p class="font-semibold text-brand-700">Bắt đầu hành trình</p><h1 class="mt-1 text-3xl font-black text-ink">Tạo tài khoản</h1><form class="mt-7 grid gap-5 sm:grid-cols-2" method="POST" action="{{ route('register.store') }}">@csrf
    <label class="sm:col-span-2">
        <span class="label">Họ và tên</span>
        <input class="field @error('full_name') border-red-500 @enderror" name="full_name" maxlength="100" value="{{ \App\Support\CustomerValidation::text(old('full_name')) }}" autocomplete="name" required>
        @error('full_name') <span class="text-xs font-semibold text-red-600 mt-1 block">{{ $message }}</span> @enderror
    </label>
    <label>
        <span class="label">Email</span>
        <input class="field @error('email') border-red-500 @enderror" type="email" name="email" maxlength="100" value="{{ \App\Support\CustomerValidation::text(old('email')) }}" required>
        @error('email') <span class="text-xs font-semibold text-red-600 mt-1 block">{{ $message }}</span> @enderror
    </label>
    <label>
        <span class="label">Số điện thoại</span>
        <input class="field @error('phone') border-red-500 @enderror" name="phone" type="tel" maxlength="12" pattern="(0|\+84)[0-9]{9}" title="Ví dụ: 0901234567 hoặc +84901234567" value="{{ \App\Support\CustomerValidation::text(old('phone')) }}" required>
        @error('phone') <span class="text-xs font-semibold text-red-600 mt-1 block">{{ $message }}</span> @enderror
    </label>
    <label class="sm:col-span-2">
        <span class="label">Địa chỉ</span>
        <input class="field @error('address') border-red-500 @enderror" name="address" maxlength="255" value="{{ \App\Support\CustomerValidation::text(old('address')) }}">
        @error('address') <span class="text-xs font-semibold text-red-600 mt-1 block">{{ $message }}</span> @enderror
    </label>
    <label>
        <span class="label">Mật khẩu</span>
        <input class="field @error('password') border-red-500 @enderror" type="password" name="password" data-new-password minlength="8" maxlength="72" required>
        @error('password') <span class="text-xs font-semibold text-red-600 mt-1 block">{{ $message }}</span> @enderror
    </label>
    <label>
        <span class="label">Xác nhận mật khẩu</span>
        <input class="field" type="password" name="password_confirmation" maxlength="72" required>
    </label>
    <p class="text-xs text-muted sm:col-span-2">Mật khẩu cần ít nhất 8 ký tự, gồm chữ và số, tối đa 72 byte.</p>
    <button class="btn-primary sm:col-span-2">Tạo tài khoản</button>
</form></div></section>
@endsection
