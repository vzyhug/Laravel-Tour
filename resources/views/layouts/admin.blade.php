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
        <div class="flex items-center justify-between"><a href="{{ route(session('employee_role') === 'Guide' ? 'operations.index' : 'admin.dashboard') }}" class="text-xl font-black text-white">VietGo Admin</a><button class="rounded-lg p-2 lg:hidden" data-menu-toggle="admin-menu" aria-expanded="false" aria-label="Mở menu">☰</button></div>
        <nav id="admin-menu" class="mt-8 hidden gap-2 lg:grid" aria-label="Menu quản trị">
            @foreach([
                ['admin.dashboard','Tổng quan'], ['operations.index','Điều hành chuyến đi'], ['admin.itinerary-consultations.index','Tư vấn lịch trình'], ['admin.tours.index','Quản lý tour'], ['admin.departures.index','Lịch khởi hành'], ['admin.bookings.index','Đơn đặt tour'], ['admin.hotel-booking.index','Đặt phòng khách sạn'], ['admin.coupons.index','Mã giảm giá'], ['admin.customers.index','Khách hàng'], ['admin.employees.index','Nhân viên'], ['admin.payments.index','Thanh toán'], ['admin.provinces.index','Địa điểm']
            ] as [$name,$label])
                @if((session('employee_role') !== 'Guide' || $name === 'operations.index') && (session('employee_role') === 'Admin' || !in_array($name, ['admin.coupons.index', 'admin.employees.index', 'admin.provinces.index'], true)))
                    <a href="{{ route($name) }}" class="rounded-xl px-4 py-3 font-medium transition {{ request()->routeIs($name) ? 'bg-brand-600 text-white' : 'hover:bg-white/10 hover:text-white' }}">{{ $label }}</a>
                @endif
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
<script>
(function () {
    // --- Tín hiệu "còn mở" ---
    var PING_URL = @json(route('admin.ping'));
    function ping() {
        fetch(PING_URL, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { if (r.redirected) { location.href = r.url; } })
            .catch(function () {});
    }
    ping();
    setInterval(ping, 5000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { ping(); } });

    // --- Lưu nháp form ---
    var PREFIX = 'vietgo-draft:';
    function skip(el) {
        return !el.name || ['_token', '_method'].indexOf(el.name) > -1
            || ['password', 'file', 'hidden', 'submit', 'button'].indexOf(el.type) > -1;
    }
    document.querySelectorAll('main form').forEach(function (form, i) {
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') { return; }
        var key = PREFIX + location.pathname + ':' + i;
        try {
            var saved = JSON.parse(localStorage.getItem(key) || 'null');
            if (saved && Date.now() - saved.t < 86400000) {
                Array.prototype.forEach.call(form.elements, function (el, idx) {
                    if (skip(el) || saved.v[idx] === null || saved.v[idx] === undefined) { return; }
                    if (el.type === 'checkbox' || el.type === 'radio') { el.checked = saved.v[idx]; } else { el.value = saved.v[idx]; }
                });
            }
        } catch (e) {}
        function save() {
            try {
                var v = Array.prototype.map.call(form.elements, function (el) {
                    if (skip(el)) { return null; }
                    return (el.type === 'checkbox' || el.type === 'radio') ? el.checked : el.value;
                });
                localStorage.setItem(key, JSON.stringify({ t: Date.now(), v: v }));
            } catch (e) {}
        }
        form.addEventListener('input', save);
        form.addEventListener('change', save);
        form.addEventListener('submit', function () { try { localStorage.removeItem(key); } catch (e) {} });
    });

    // Bấm Đăng xuất thủ công thì xóa hết nháp (máy dùng chung cho an toàn)
    var logout = document.querySelector('form[action="{{ route('admin.logout') }}"]');
    if (logout) {
        logout.addEventListener('submit', function () {
            try {
                Object.keys(localStorage).filter(function (k) { return k.indexOf(PREFIX) === 0; })
                    .forEach(function (k) { localStorage.removeItem(k); });
            } catch (e) {}
        });
    }
})();
</script>
</body>
</html>
