@extends('layouts.app')
@section('title', 'Quên mật khẩu · VietGo')
@section('content')
<section class="container-page py-12"><div class="card mx-auto max-w-md p-7">
    <h1 class="text-2xl font-bold">Quên mật khẩu</h1>
    <p class="mt-3 text-sm text-muted">Nhập email tài khoản khách hàng để nhận liên kết đặt lại mật khẩu.</p>
    <form method="POST" action="{{ route('customer.password.email') }}" class="mt-6 grid gap-5">
        @csrf
        <label><span class="label">Email</span><input class="field" type="email" name="email" value="{{ \App\Support\CustomerValidation::text(old('email')) }}" autocomplete="email" maxlength="100" required></label>
        <button class="btn-primary">Gửi liên kết</button>
    </form>
    <a class="mt-5 inline-block text-brand-700" href="{{ route('login') }}">Quay lại đăng nhập</a>
</div></section>
@endsection
