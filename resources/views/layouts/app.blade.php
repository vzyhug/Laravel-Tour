<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="VietGo - đặt tour khám phá Việt Nam">
    <title>@yield('title', 'VietGo Travel')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="container-page flex min-h-18 items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg" aria-label="VietGo - Trang chủ">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-xl font-black text-white">V</span>
            <span class="text-xl font-black tracking-tight text-ink">Viet<span class="text-brand-600">Go</span></span>
        </a>
        <button type="button" class="rounded-lg p-2 text-slate-700 md:hidden" data-menu-toggle="main-menu" aria-expanded="false" aria-label="Mở menu">
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <nav id="main-menu" class="absolute inset-x-0 top-18 hidden border-b bg-white p-4 shadow-lg md:static md:flex md:items-center md:gap-2 md:border-0 md:p-0 md:shadow-none" aria-label="Điều hướng chính">
            <a href="{{ route('home') }}" class="block rounded-lg px-4 py-2.5 font-medium hover:bg-brand-50 hover:text-brand-700">Trang chủ</a>
            <a href="{{ route('tours.index') }}" class="block rounded-lg px-4 py-2.5 font-medium hover:bg-brand-50 hover:text-brand-700">Tour du lịch</a>
            @if(session('customer_id'))
                <a href="{{ route('customer.dashboard') }}" class="block rounded-lg px-4 py-2.5 font-medium hover:bg-brand-50 hover:text-brand-700">Chuyến đi của tôi</a>
                <a href="{{ route('favorites.index') }}" class="block rounded-lg px-4 py-2.5 font-medium hover:bg-brand-50 hover:text-brand-700">♥ Yêu thích</a>
                <form action="{{ route('logout') }}" method="POST" class="md:ml-2">@csrf<button class="btn-secondary w-full">Đăng xuất</button></form>
            @else
                <a href="{{ route('login') }}" class="block rounded-lg px-4 py-2.5 font-semibold text-brand-700">Đăng nhập</a>
                <a href="{{ route('register') }}" class="btn-primary mt-2 w-full md:mt-0 md:w-auto">Đăng ký</a>
            @endif
        </nav>
    </div>
</header>

@include('partials.flash')
<main>@yield('content')</main>

<footer class="mt-20 bg-ink text-slate-300">
    <div class="container-page grid gap-10 py-12 md:grid-cols-3">
        <div><div class="text-xl font-black text-white">VietGo Travel</div><p class="mt-3 max-w-sm text-sm leading-6">Đồng hành cùng bạn trên mọi hành trình khám phá vẻ đẹp Việt Nam.</p></div>
        <div><h2 class="font-bold text-white">Liên kết</h2><div class="mt-3 grid gap-2 text-sm"><a class="hover:text-white" href="{{ route('tours.index') }}">Tất cả tour</a><a class="hover:text-white" href="{{ route('customer.dashboard') }}">Quản lý đặt tour</a><a class="hover:text-white" href="{{ asset('images/tours/demo/credits.html') }}">Nguồn ảnh địa danh</a></div></div>
        <div><h2 class="font-bold text-white">Liên hệ</h2><p class="mt-3 text-sm">Hotline: 1900 6868<br>Email: hello@vietgo.test</p></div>
    </div>
</footer>
@stack('scripts')
</body>
</html>
