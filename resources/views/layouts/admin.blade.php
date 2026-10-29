<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f6f7">
    <title>@yield('title', session('employee_role') === 'Guide' ? 'Điều hành chuyến đi' : 'Quản trị') · VietGo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class(['admin-app min-h-screen antialiased', 'guide-app bg-sand text-ink' => session('employee_role') === 'Guide', 'bg-[#f5f6f7] text-[#202522]' => session('employee_role') !== 'Guide'])>
@php
    $employeeRole = session('employee_role');
    $navigation = [
        'Vận hành' => [
            ['admin.dashboard', 'Tổng quan', 'dashboard'],
            ['operations.index', 'Điều hành chuyến đi', 'briefcase'],
            ['admin.itinerary-consultations.index', 'Tư vấn lịch trình', 'map'],
            ['admin.departures.index', 'Lịch khởi hành', 'calendar'],
            ['admin.bookings.index', 'Đơn đặt tour', 'ticket'],
        ],
        'Sản phẩm & dịch vụ' => [
            ['admin.tours.index', 'Tour du lịch', 'map'],
            ['admin.hotel-booking.index', 'Đặt phòng khách sạn', 'bed'],
            ['admin.provinces.index', 'Địa điểm', 'pin'],
        ],
        'Kinh doanh' => [
            ['admin.customers.index', 'Khách hàng', 'users'],
            ['admin.coupons.index', 'Mã giảm giá', 'tag'],
            ['admin.payments.index', 'Thanh toán', 'credit-card'],
        ],
        'Quản trị hệ thống' => [
            ['admin.employees.index', 'Nhân viên', 'user'],
        ],
    ];
@endphp
<div class="admin-shell min-h-screen lg:grid lg:grid-cols-[264px_minmax(0,1fr)]">
    <aside class="admin-sidebar z-40 border-b border-[#e8eae7] bg-white lg:sticky lg:top-0 lg:h-screen lg:border-b-0 lg:border-r">
        <div class="flex h-[76px] items-center border-b border-[#eef0ed] px-5 lg:px-6">
            <a href="{{ route($employeeRole === 'Guide' ? 'operations.index' : 'admin.dashboard') }}" class="flex items-center gap-3 rounded-lg" aria-label="{{ $employeeRole === 'Guide' ? 'VietGo Điều hành chuyến đi' : 'VietGo Quản trị' }}">
                <span class="admin-brand-mark"><x-icon name="pin" class="size-5"/></span>
                <span><strong class="block text-[15px] font-extrabold tracking-tight text-[#252a26]">VietGo</strong><small class="block text-[9px] font-bold uppercase tracking-[.17em] text-[#818a83]">Bảng điều hành</small></span>
            </a>
        </div>

        <nav id="admin-menu" class="admin-navigation hidden px-3 pb-5 pt-4 lg:block lg:h-[calc(100vh-76px)] lg:overflow-y-auto" aria-label="Menu quản trị">
            @foreach($navigation as $section => $items)
                @php
                    $visibleItems = collect($items)->filter(function ($item) use ($employeeRole) {
                        [$name] = $item;
                        return ($employeeRole !== 'Guide' || $name === 'operations.index')
                            && ($employeeRole === 'Admin' || ! in_array($name, ['admin.coupons.index', 'admin.employees.index', 'admin.provinces.index'], true));
                    });
                @endphp
                @if($visibleItems->isNotEmpty())
                    <p class="admin-nav-heading">{{ $section }}</p>
                    <div class="mb-5 grid gap-1">
                        @foreach($visibleItems as [$name, $label, $icon])
                            @php($active = request()->routeIs($name) || (str_ends_with($name, '.index') && request()->routeIs(str_replace('.index', '.*', $name))))
                            <a href="{{ route($name) }}" @if($active) aria-current="page" @endif @class(['admin-nav-link', 'admin-nav-link-active' => $active])>
                                <x-icon :name="$icon" class="size-[18px] shrink-0"/>
                                <span>{{ $label }}</span>
                                @if($active)<span class="admin-nav-indicator" aria-hidden="true"></span>@endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach

            <div class="mt-8 border-t border-[#eef0ed] pt-4">
                <a href="{{ route('home') }}" class="admin-nav-link"><x-icon name="arrow-up" class="size-[18px] shrink-0"/><span>Xem website</span></a>
                <form action="{{ route('admin.logout') }}" method="POST" class="mt-1">
                    @csrf
                    <button class="admin-nav-link w-full text-left"><x-icon name="close" class="size-[18px] shrink-0"/><span>Đăng xuất</span></button>
                </form>
            </div>
            <div class="admin-sidebar-footer"><span class="admin-avatar">{{ mb_substr((string) session('employee_name', 'V'), 0, 1) }}</span><span class="min-w-0"><strong class="block truncate text-[11px] text-[#29312c]">{{ session('employee_name') }}</strong><small class="mt-0.5 block text-[9px] text-[#7d8780]">{{ $employeeRole === 'Admin' ? 'Qu?n tr? vi?n' : 'H??ng d?n vi?n' }}</small></span></div>
        </nav>
    </aside>

    <div class="min-w-0">
        <header class="admin-topbar sticky top-0 z-30 flex h-[76px] items-center justify-between gap-4 border-b border-[#e8eae7] bg-white/95 px-5 backdrop-blur-md sm:px-7 xl:px-10">
            <div class="flex min-w-0 items-center gap-3">
                <button class="admin-topbar-menu lg:hidden" data-menu-toggle="admin-menu" aria-expanded="false" aria-label="Mở menu"><x-icon name="menu" class="size-5"/></button>
                <div class="min-w-0"><p class="truncate text-[9px] font-bold uppercase tracking-[.16em] text-[#8a948d]">VietGo <span class="px-1 text-[#c3c9c4]">/</span> {{ $employeeRole === 'Guide' ? 'Điều hành chuyến đi' : 'Quản trị' }}</p><p class="mt-0.5 truncate text-xs font-semibold text-[#303832]">{{ session('employee_name') }}</p></div>
            </div>
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <span class="admin-role-chip"><span></span>{{ $employeeRole === 'Admin' ? 'Qu?n tr? vi?n' : 'H??ng d?n vi?n' }}</span>
                <a class="admin-site-link" href="{{ route('home') }}">Mở website <span aria-hidden="true">↗</span></a>
            </div>
        </header>
        @include('partials.flash')
        <main class="admin-main px-4 py-6 sm:px-6 lg:px-8 xl:px-10">@yield('content')</main>
    </div>
</div>
</body>
</html>
