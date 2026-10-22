<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
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
        <nav id="main-menu" class="absolute inset-x-0 top-20 hidden border-b border-[#e7dcd1] bg-sand p-5 shadow-lg lg:static lg:flex lg:items-center lg:gap-1 lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none" aria-label="Điều hướng chính">
            <a href="{{ route('home') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>Khám phá</a>
            <a href="{{ route('tours.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('tours.*', 'provinces.tours')]) @if(request()->routeIs('tours.*', 'provinces.tours')) aria-current="page" @endif>Tour du lịch</a>
            <a href="{{ route('home') }}#destinations" class="nav-link">Điểm đến</a>
            <a href="{{ route('home') }}#why-vietgo" class="nav-link">Về VietGo</a>
            @if(session('customer_id'))
                <a href="{{ route('customer.dashboard') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('customer.*')])>Chuyến đi của tôi</a>
            @endif
        </nav>
        <div class="flex items-center gap-1.5 sm:gap-3">
            <a href="{{ route('tours.index') }}" class="hidden items-center gap-2 rounded-lg bg-linen px-3 py-2.5 text-[11px] text-muted xl:flex"><x-icon name="search" class="size-4"/> Tìm hành trình</a>
            <span class="hidden text-[10px] font-semibold text-muted sm:block">VND ₫</span>
            <a href="{{ route('favorites.index') }}" class="icon-button size-10 border-0 bg-transparent" aria-label="Yêu thích"><x-icon name="bookmark"/></a>
            @if(session('customer_id'))
                <a href="{{ route('customer.dashboard') }}" class="grid size-9 place-items-center rounded-full bg-brand-100 font-serif text-lg text-brand-700" aria-label="Tài khoản của tôi">{{ mb_substr(session('customer_name', 'V'), 0, 1) }}</a>
            @elseif(session('employee_id'))
                <a href="{{ route('admin.dashboard') }}" class="text-xs font-semibold text-brand-600">Quản trị</a>
            @else
                <a href="{{ route('login') }}" class="text-xs font-semibold text-brand-600">Đăng nhập</a>
            @endif
            <button type="button" class="icon-button size-10 border-0 bg-transparent lg:hidden" data-menu-toggle="main-menu" aria-controls="main-menu" aria-expanded="false" aria-label="Mở menu"><x-icon name="menu"/></button>
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
            <div><p class="eyebrow text-muted">Cùng VietGo</p><div class="mt-5 grid gap-3 text-xs text-muted"><a href="{{ route('home') }}#why-vietgo" class="hover:text-brand-600">Cam kết của chúng tôi</a><a href="{{ route('customer.dashboard') }}" class="hover:text-brand-600">Quản lý chuyến đi</a><a href="{{ route('favorites.index') }}" class="hover:text-brand-600">Tour yêu thích</a><a href="{{ asset('images/tours/demo/credits.html') }}" class="hover:text-brand-600">Nguồn ảnh địa danh</a></div></div>
            <div><p class="eyebrow text-muted">Kết nối & tư vấn</p><p class="mt-5 text-xs leading-7 text-muted"><a href="mailto:hello@vietgo.test">hello@vietgo.test</a><br><a href="tel:19006868">Hotline: 1900 6868</a></p><p class="mt-5 font-serif text-lg italic text-brand-600">Đi xa hơn, an tâm hơn.</p></div>
        </div>
        <div class="mt-12 flex flex-wrap items-center justify-between gap-4 border-t border-[#e5d9cf] pt-6 text-[10px] text-muted"><p>© {{ date('Y') }} VietGo Travel. Đồng hành trên mọi hành trình.</p><span class="flex items-center gap-2"><x-icon name="leaf" class="size-4 text-sage"/> Trân trọng thiên nhiên, kết nối văn hóa.</span></div>
    </div>
</footer>
@stack('scripts')
</body>
</html>
