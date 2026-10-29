<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="VietGo – Những hành trình chọn lọc, khám phá vẻ đẹp Việt Nam theo cách của bạn.">
    <title>@yield('title', 'VietGo · Hành trình chọn lọc')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="travel-site min-h-screen">
<a href="#main-content" class="sr-only z-50 rounded bg-white p-3 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Đến nội dung chính</a>
<header class="sticky top-0 z-40 border-b border-[#e7dcd1]/70 bg-sand/95 backdrop-blur-xl">
    <div class="container-page flex h-20 items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="VietGo – Trang chủ">
            <svg class="hidden size-9 text-brand-600 min-[380px]:block" viewBox="0 0 40 40" fill="none" aria-hidden="true"><path d="M5 29 20 6l15 23H5ZM12 29 20 17l8 12M8 35h24" stroke="currentColor" stroke-width="1.5"/><circle cx="29" cy="9" r="3" fill="currentColor"/></svg>
            <span><span class="block font-serif text-[24px] font-medium uppercase leading-none tracking-[.12em]">VietGo</span><span class="mt-1.5 block text-[8px] uppercase tracking-[.28em] text-muted">Hành trình chọn lọc</span></span>
        </a>
        <nav id="main-menu" class="absolute inset-x-0 top-20 hidden border-b border-[#e7dcd1] bg-sand p-5 shadow-lg xl:static xl:flex xl:items-center xl:gap-1 xl:border-0 xl:bg-transparent xl:p-0 xl:shadow-none" aria-label="Điều hướng chính">
            <a href="{{ route('home') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>Khám phá</a>
            <a href="{{ route('tours.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('tours.*', 'provinces.tours')]) @if(request()->routeIs('tours.*', 'provinces.tours')) aria-current="page" @endif>Tour du lịch</a>
            <a href="{{ route('destinations.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('destinations.*', 'places.*')])>Điểm đến</a>
            <a href="{{ route('hotels.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('hotels.*')])>Khách sạn</a>
            <a href="{{ route('home') }}#why-vietgo" class="nav-link">Về VietGo</a>
            @if(session('customer_id'))
                <a href="{{ route('customer.dashboard') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('customer.*')])>Chuyến đi của tôi</a>
            @endif
        </nav>
        <div class="flex items-center gap-1.5 sm:gap-3">
            <a href="{{ route('tours.index') }}" class="hidden items-center gap-2 rounded-lg bg-linen px-3 py-2.5 text-[11px] text-muted xl:flex"><x-icon name="search" class="size-4"/> Tìm hành trình</a>
            <a href="{{ route('favorites.index') }}" class="icon-button size-10 border-0 bg-transparent" aria-label="Yêu thích"><x-icon name="bookmark"/></a>
            @if(session('customer_id') || session('employee_id'))
                @php
                    $isCustomer = (bool) session('customer_id');
                    $accountName = $isCustomer ? session('customer_name', 'Khách hàng') : session('employee_name', 'VietGo');
                    $accountRole = $isCustomer ? 'Khách hàng' : (session('employee_role') === 'Guide' ? 'Hướng dẫn viên' : 'Quản trị viên');
                    $accountRoute = $isCustomer ? 'customer.dashboard' : (session('employee_role') === 'Guide' ? 'operations.index' : 'admin.dashboard');
                @endphp
                <details class="relative" data-account-menu>
                    <summary class="grid size-10 cursor-pointer list-none place-items-center rounded-full border border-brand-100 bg-brand-100 font-serif text-lg text-brand-700 transition hover:border-brand-500 [&::-webkit-details-marker]:hidden" aria-label="Mở tài khoản: {{ $accountName }}">
                        {{ mb_substr((string) $accountName, 0, 1) }}
                    </summary>
                    <div class="absolute right-0 top-full z-50 w-60 max-w-[calc(100vw-32px)] pt-2">
                        <nav class="rounded-2xl border border-[#e7dcd1] bg-white p-2 shadow-xl" aria-label="Tài khoản cá nhân">
                            <div class="border-b border-[#eee5dd] px-3 py-3"><p class="truncate text-xs font-semibold text-ink">{{ $accountName }}</p><p class="mt-1 text-[10px] text-muted">{{ $accountRole }}</p></div>
                            <a href="{{ route($accountRoute) }}" class="mt-1 flex min-h-11 items-center gap-2 rounded-lg px-3 text-xs font-semibold text-ink hover:bg-linen"><x-icon name="user" class="size-4 text-brand-600"/>{{ $isCustomer ? 'Tài khoản của tôi' : (session('employee_role') === 'Guide' ? 'Điều hành chuyến đi' : 'Trang quản trị') }}</a>
                            <form method="POST" action="{{ route($isCustomer ? 'logout' : 'admin.logout') }}">
                                @csrf
                                <button type="submit" class="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 text-left text-xs font-semibold text-brand-700 hover:bg-brand-50"><x-icon name="arrow" class="size-4"/>Đăng xuất</button>
                            </form>
                        </nav>
                    </div>
                </details>
            @else
                <a href="{{ route('login') }}" class="text-xs font-semibold text-brand-600">Đăng nhập</a>
            @endif
            <button type="button" class="icon-button size-10 border-0 bg-transparent xl:hidden" data-menu-toggle="main-menu" aria-controls="main-menu" aria-expanded="false" aria-label="Mở menu"><x-icon name="menu"/></button>
        </div>
    </div>
</header>
@include('partials.flash')
<main id="main-content">@yield('content')</main>
<footer class="mt-16 border-t border-[#e8ddd3] bg-[#f9f2ed] sm:mt-24">
    <div class="container-page py-12 sm:py-16">
        <div class="grid gap-10 lg:grid-cols-[1.5fr_1fr_1fr_1fr]">
            <div>
                <a href="{{ route('home') }}" class="font-serif text-2xl uppercase tracking-[.12em]">VietGo</a>
                <p class="mt-5 max-w-sm font-serif text-2xl leading-snug">Những hành trình chậm,<br>những trải nghiệm <em class="text-brand-600">sâu.</em></p>
                <p class="mt-4 max-w-xs text-xs leading-6 text-muted">Khám phá Việt Nam qua những điểm đến chọn lọc, văn hóa bản địa và những khoảnh khắc dành riêng cho bạn.</p>
                <a class="text-link mt-4" href="{{ route('tours.index') }}">Tìm hành trình của bạn <x-icon name="arrow" class="size-4"/></a>
            </div>
            <div><p class="eyebrow text-muted">Điểm đến</p><div class="mt-5 grid gap-3 text-xs text-muted"><a href="{{ route('tours.index', ['region' => 'Bắc']) }}" class="hover:text-brand-600">Miền Bắc · Núi rừng & di sản</a><a href="{{ route('tours.index', ['region' => 'Trung']) }}" class="hover:text-brand-600">Miền Trung · Biển & văn hóa</a><a href="{{ route('tours.index', ['region' => 'Nam']) }}" class="hover:text-brand-600">Miền Nam · Miệt vườn & đảo</a><a href="{{ route('tours.index') }}" class="hover:text-brand-600">Tất cả hành trình</a></div></div>
            <div><p class="eyebrow text-muted">Cùng VietGo</p><div class="mt-5 grid gap-3 text-xs text-muted"><a href="{{ route('home') }}#why-vietgo" class="hover:text-brand-600">Cam kết của chúng tôi</a>@if(session('employee_role') === 'Guide')<a href="{{ route('operations.index') }}" class="hover:text-brand-600">Chuyến được phân công</a>@else<a href="{{ route('customer.dashboard') }}" class="hover:text-brand-600">Quản lý chuyến đi</a>@endif<a href="{{ route('favorites.index') }}" class="hover:text-brand-600">Tour yêu thích</a></div></div>
            <div><p class="eyebrow text-muted">Kết nối & tư vấn</p><p class="mt-5 text-xs leading-7 text-muted"><a href="mailto:hello@vietgo.test">hello@vietgo.test</a><br><a href="tel:19006868">Hotline: 1900 6868</a></p><p class="mt-5 font-serif text-lg italic text-brand-600">Đi xa hơn, an tâm hơn.</p></div>
        </div>
        <div class="mt-12 flex flex-wrap items-center justify-between gap-4 border-t border-[#e5d9cf] pt-6 text-[10px] text-muted"><p>© {{ date('Y') }} VietGo Travel. Đồng hành trên mọi hành trình.</p><span class="flex items-center gap-2"><x-icon name="leaf" class="size-4 text-sage"/> Trân trọng thiên nhiên, kết nối văn hóa.</span></div>
    </div>
</footer>
<x-image-viewer/>
@stack('scripts')
</body>
</html>
