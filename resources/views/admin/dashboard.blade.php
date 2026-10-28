@extends('layouts.admin')

@section('title', 'Tổng quan')

@section('content')
    @php
        $hour = now()->hour;
        $greeting = $hour < 11 ? 'Chào buổi sáng' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối');
        $statusLabels = ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'];
        $statusClasses = ['pending' => 'admin-status-pending', 'confirmed' => 'admin-status-confirmed', 'completed' => 'admin-status-completed', 'cancelled' => 'admin-status-cancelled'];
        $metrics = [
            ['Tour đang hoạt động', $stats['tours'], 'map', 'Danh mục hành trình'],
            ['Khách hàng', $stats['customers'], 'users', 'Tài khoản trong hệ thống'],
            ['Đơn đặt tour', $stats['bookings'], 'ticket', 'Tổng số đơn đã ghi nhận'],
            ['Doanh thu đã thu', number_format($stats['revenue'], 0, ',', '.').'₫', 'credit-card', 'Từ các khoản thanh toán hoàn tất'],
        ];
        $shortcuts = [
            ['admin.tours.create', 'Thêm tour mới', 'Tạo hành trình và cập nhật nội dung', 'plus'],
            ['admin.departures.create', 'Mở lịch khởi hành', 'Thiết lập ngày đi và số chỗ', 'calendar'],
            ['admin.bookings.index', 'Theo dõi đơn đặt', 'Xem và xử lý yêu cầu đặt tour', 'ticket'],
        ];
    @endphp

    <section class="admin-page-heading">
        <div>
            <p class="admin-page-eyebrow">{{ $greeting }}, {{ session('employee_name') }} <span class="px-1 text-[#c2c9c3]">·</span> {{ now()->format('d/m/Y') }}</p>
            <h1 class="admin-page-title">Tổng quan vận hành</h1>
            <p class="mt-2 text-[11px] leading-5 text-[#78837b]">Tình hình hoạt động và các công việc cần xử lý trong VietGo.</p>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-[#28745a] px-4 py-2 text-[10px] font-semibold text-white shadow-sm transition hover:bg-[#205d48]">
            <x-icon name="ticket" class="size-4"/> Xem đơn đặt tour
        </a>
    </section>

    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Chỉ số tổng quan">
        @foreach($metrics as [$label, $value, $icon, $description])
            <article class="admin-kpi-card">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-[10px] font-semibold text-[#778179]">{{ $label }}</p><p class="mt-3 font-serif text-[30px] leading-none tracking-tight text-[#222923]">{{ $value }}</p></div>
                    <span class="admin-kpi-icon"><x-icon :name="$icon" class="size-[19px]"/></span>
                </div>
                <p class="mt-4 border-t border-[#f0f2f0] pt-3 text-[9px] text-[#89928b]">{{ $description }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-7">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div><p class="admin-page-eyebrow">Thao tác nhanh</p><h2 class="mt-1 text-sm font-bold text-[#2c342e]">Bắt đầu công việc</h2></div>
        </div>
        <div class="grid gap-3 md:grid-cols-3">
            @foreach($shortcuts as [$routeName, $label, $description, $icon])
                <a href="{{ route($routeName) }}" class="admin-quick-link">
                    <span class="admin-quick-icon"><x-icon :name="$icon" class="size-[18px]"/></span>
                    <span class="min-w-0 flex-1"><strong class="block text-[11px] font-semibold text-[#2c342e]">{{ $label }}</strong><small class="mt-1 block text-[9px] leading-4 text-[#818b83]">{{ $description }}</small></span>
                    <x-icon name="arrow" class="size-4 shrink-0 text-[#94a098]"/>
                </a>
            @endforeach
        </div>
    </section>

    <section class="admin-panel mt-7">
        <div class="admin-panel-heading">
            <div><p class="admin-page-eyebrow">Hoạt động gần đây</p><h2 class="mt-1 text-sm font-bold text-[#2c342e]">Đơn đặt tour mới nhất</h2></div>
            <a href="{{ route('admin.bookings.index') }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg px-3 text-[10px] font-semibold text-[#28745a] transition hover:bg-[#edf5ef]">Tất cả đơn <x-icon name="arrow" class="size-3.5"/></a>
        </div>
        <div class="overflow-x-auto">
            <table class="admin-data-table">
                <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Hành trình</th><th>Ngày đặt</th><th class="text-right">Tổng tiền</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody>
                    @forelse($recentBookings as $booking)
                        <tr>
                            <td><a href="{{ route('admin.bookings.show', $booking) }}" class="font-semibold text-[#28745a] hover:underline">{{ $booking->booking_code }}</a></td>
                            <td><span class="font-medium text-[#303832]">{{ $booking->customer->full_name }}</span><small class="mt-1 block text-[9px] text-[#89928b]">{{ $booking->customer->email }}</small></td>
                            <td><span class="block max-w-[250px] truncate font-medium text-[#303832]">{{ $booking->departure->tour->title }}</span><small class="mt-1 block text-[9px] text-[#89928b]">{{ $booking->departure->departure_date->format('d/m/Y') }}</small></td>
                            <td class="whitespace-nowrap text-[#657068]">{{ $booking->booking_date->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap text-right font-semibold text-[#303832]">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</td>
                            <td><span @class(['admin-status', $statusClasses[$booking->status] ?? 'bg-slate-100 text-slate-600'])>{{ $statusLabels[$booking->status] ?? $booking->status }}</span></td>
                            <td class="text-right"><a href="{{ route('admin.bookings.show', $booking) }}" class="grid size-8 place-items-center rounded-lg text-[#818b83] transition hover:bg-[#edf5ef] hover:text-[#28745a]" aria-label="Xem đơn {{ $booking->booking_code }}"><x-icon name="arrow-up" class="size-4"/></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-14 text-center"><span class="mx-auto grid size-11 place-items-center rounded-xl bg-[#f2f5f2] text-[#879188]"><x-icon name="ticket"/></span><p class="mt-3 text-[11px] font-semibold text-[#4b554d]">Chưa có đơn đặt tour</p><p class="mt-1 text-[10px] text-[#89928b]">Các đơn mới sẽ xuất hiện tại đây.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
