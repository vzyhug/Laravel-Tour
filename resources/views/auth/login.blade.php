@extends('layouts.app')
@section('title', 'Đăng nhập · VietGo')
@section('content')
<section class="container-page grid min-h-[70vh] place-items-center py-12">
    <div class="card w-full max-w-md p-7 sm:p-9">
        <p class="font-semibold text-brand-700">Chào mừng trở lại</p>
        <h1 class="mt-1 text-3xl font-black text-ink">Đăng nhập</h1>
        <form class="mt-7 grid gap-5" method="POST" action="{{ route('login.store') }}">
            @csrf
            <label>
                <span class="label">Email hoặc tên đăng nhập</span>
                <input class="field" type="text" name="login" maxlength="255" value="{{ \App\Support\CustomerValidation::text(old('login')) }}" autocomplete="username" required>
            </label>
            <label>
                <span class="label">Mật khẩu</span>
                <span style="position:relative;display:block">
                    <input id="password" class="field" style="padding-right:4.5rem" type="password" name="password" maxlength="1000" autocomplete="current-password" required>
                    <button type="button" class="text-brand-700" style="position:absolute;top:0;bottom:0;right:1rem;font-size:.875rem;font-weight:600"
                        onclick="const i=document.getElementById('password');const s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Ẩn':'Hiện';">Hiện</button>
                </span>
            </label>
            <button class="btn-primary w-full">Đăng nhập</button>
        </form>
        <a class="mt-4 block text-sm text-brand-700" href="{{ route('customer.password.request') }}">Quên mật khẩu khách hàng?</a>
        <p class="mt-6 text-center text-sm text-slate-500">Chưa có tài khoản? <a class="font-semibold text-brand-700 hover:underline" href="{{ route('register') }}">Đăng ký ngay</a></p>
    </div>
</section>
@endsection
