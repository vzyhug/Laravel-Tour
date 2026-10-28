@extends('layouts.app')
@section('title', 'Đổi mật khẩu · VietGo')
@section('content')
<section class="container-page py-12"><div class="card mx-auto max-w-md p-7">
    <h1 class="text-2xl font-bold">Đổi mật khẩu</h1>
    <p class="mt-3 text-sm text-muted">Các phiên đăng nhập khác sẽ được đăng xuất sau khi đổi mật khẩu.</p>
    <form method="POST" action="{{ route('customer.password.change.update') }}" class="mt-6 grid gap-5">
        @csrf @method('PUT')
        <label><span class="label">Mật khẩu hiện tại</span><input class="field" type="password" name="current_password" autocomplete="current-password" required></label>
        <label><span class="label">Mật khẩu mới</span><input class="field" type="password" name="password" minlength="8" autocomplete="new-password" required></label>
        <label><span class="label">Xác nhận mật khẩu mới</span><input class="field" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
        <button class="btn-primary">Đổi mật khẩu</button>
    </form>
</div></section>
@endsection
