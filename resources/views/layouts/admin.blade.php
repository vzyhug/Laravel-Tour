<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Quản trị') · VietGo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100">
<div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="bg-ink p-5 text-slate-300 lg:min-h-screen">
        <div class="flex items-center justify-between"><a href="{{ route('admin.dashboard') }}" class="text-xl font-black text-white">VietGo Admin</a><button class="rounded-lg p-2 lg:hidden" data-menu-toggle="admin-menu" aria-expanded="false" aria-label="Mở menu">☰</button></div>
        <nav id="admin-menu" class="mt-8 hidden gap-2 lg:grid" aria-label="Menu quản trị">
            @if(in_array(session('employee_role'), ['Admin','Sale']))<a class="rounded-xl px-4 py-3 font-medium hover:bg-white/10" href="{{ route('admin.services.products.index') }}">Danh mục lưu trú & dịch vụ</a><a class="rounded-xl px-4 py-3 font-medium hover:bg-white/10" href="{{ route('admin.services.orders.index') }}">Đơn dịch vụ & ngoại lệ</a>@endif
            @foreach([
                ['admin.dashboard','Tổng quan'], ['admin.tours.index','Quản lý tour'], ['admin.departures.index','Lịch khởi hành'], ['admin.bookings.index','Đơn đặt tour'], ['admin.coupons.index','Mã giảm giá'], ['admin.customers.index','Khách hàng'], ['admin.employees.index','Nhân viên'], ['admin.payments.index','Thanh toán'], ['admin.provinces.index','Địa điểm']
            ] as [$name,$label])
                <a href="{{ route($name) }}" class="rounded-xl px-4 py-3 font-medium transition {{ request()->routeIs($name) ? 'bg-brand-600 text-white' : 'hover:bg-white/10 hover:text-white' }}">{{ $label }}</a>
            @endforeach
            <form action="{{ route('admin.logout') }}" method="POST" class="mt-6">@csrf<button class="w-full rounded-xl border border-white/20 px-4 py-3 text-left font-medium hover:bg-white/10">Đăng xuất</button></form>
        </nav>
    </aside>
    <div>
        <header class="border-b bg-white"><div class="flex min-h-18 items-center justify-between px-5 lg:px-8"><div><p class="text-xs font-semibold uppercase tracking-widest text-brand-600">Hệ thống điều hành</p><p class="font-bold text-slate-900">{{ session('employee_name') }}</p></div><a class="text-sm font-semibold text-brand-700 hover:underline" href="{{ route('home') }}">Xem website →</a></div></header>
        @include('partials.flash')
        <main class="p-5 lg:p-8">@yield('content')</main>
    </div>
</div>
</body>
</html>
