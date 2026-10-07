@extends('layouts.app')
@section('title', 'Đăng nhập · VietGo')
@section('content')
<section class="container-page grid min-h-[70vh] place-items-center py-12"><div class="card w-full max-w-md p-7 sm:p-9"><p class="font-semibold text-brand-700">Chào mừng trở lại</p><h1 class="mt-1 text-3xl font-black text-ink">Đăng nhập</h1><form class="mt-7 grid gap-5" method="POST" action="{{ route('login.store') }}">@csrf<label><span class="label">Email</span><input class="field" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label><label><span class="label">Mật khẩu</span><input class="field" type="password" name="password" autocomplete="current-password" required></label><button class="btn-primary w-full">Đăng nhập</button></form><p class="mt-6 text-center text-sm text-slate-500">Chưa có tài khoản? <a class="font-semibold text-brand-700 hover:underline" href="{{ route('register') }}">Đăng ký ngay</a></p></div></section>
@endsection
