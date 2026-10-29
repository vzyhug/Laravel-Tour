@extends('layouts.app')
@section('title', 'Đặt lại mật khẩu · VietGo')
@section('content')
<section class="container-page py-12"><div class="card mx-auto max-w-md p-7">
    <h1 class="text-2xl font-bold">Đặt lại mật khẩu</h1>
    <form method="POST" action="{{ route('customer.password.update') }}" class="mt-6 grid gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label><span class="label">Email</span><input class="field" type="email" name="email" value="{{ \App\Support\CustomerValidation::text(old('email', $email)) }}" autocomplete="email" required></label>
        <label><span class="label">Mật khẩu mới</span><input class="field" type="password" name="password" data-new-password maxlength="72" minlength="8" autocomplete="new-password" required></label>
        <label><span class="label">Xác nhận mật khẩu mới</span><input class="field" type="password" name="password_confirmation" maxlength="72" minlength="8" autocomplete="new-password" required></label>
        <p class="text-xs text-muted sm:col-span-2">Mật khẩu cần ít nhất 8 ký tự, gồm chữ và số, tối đa 72 byte.</p>
    <button class="btn-primary">Lưu mật khẩu mới</button>
    </form>
</div></section>
@endsection
