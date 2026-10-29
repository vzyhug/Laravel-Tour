<nav class="mb-7 flex flex-wrap gap-2" aria-label="Quản lý tài khoản">
    @foreach([['customer.dashboard', 'Chuyến đi của tôi', 'map'], ['hotel-bookings.index', 'Đơn lưu trú', 'bed'], ['favorites.index', 'Yêu thích', 'bookmark'], ['customer.profile', 'Hồ sơ', 'user']] as [$name, $label, $icon])
        <a href="{{ route($name) }}" @class(['inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-xs font-semibold transition', 'border-brand-600 bg-brand-600 text-white' => request()->routeIs($name), 'border-[#e4d8cd] bg-white text-muted hover:border-brand-500 hover:text-brand-700' => !request()->routeIs($name)]) @if(request()->routeIs($name)) aria-current="page" @endif><x-icon :name="$icon" class="size-4"/>{{ $label }}</a>
    @endforeach
</nav>
