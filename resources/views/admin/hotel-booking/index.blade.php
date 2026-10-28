@extends('layouts.admin')

@section('title', 'Đặt phòng khách sạn')

@section('content')
    @php
        $bookingStatuses = [
            'pending' => ['Chờ thanh toán', 'hotel-status-pending'],
            'confirmed' => ['Đã xác nhận', 'hotel-status-confirmed'],
            'completed' => ['Hoàn thành', 'hotel-status-completed'],
            'cancelled' => ['Đã hủy', 'hotel-status-cancelled'],
            'expired' => ['Hết hạn', 'hotel-status-expired'],
        ];
        $paymentStatuses = ['completed' => 'Đã thanh toán', 'refunded' => 'Đã hoàn tiền', 'pending' => 'Chờ thanh toán', 'failed' => 'Thất bại'];
    @endphp

    <section class="admin-page-heading">
        <div>
            <p class="admin-page-eyebrow">Khách sạn · Quản lý đặt phòng</p>
            <h1 class="admin-page-title">Đặt phòng khách sạn</h1>
            <p class="mt-2 max-w-2xl text-xs leading-6 text-[#78837b]">Quản lý đơn lưu trú, loại phòng, giá và số phòng mở bán theo từng đêm.</p>
        </div>
        <a href="#hotel-bookings" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-[#28745a] px-4 py-2 text-[10px] font-semibold text-white shadow-sm transition hover:bg-[#205d48]">
            <x-icon name="bed" class="size-4" /> Xem đơn lưu trú
        </a>
    </section>

    <section class="mt-6 grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="admin-panel">
            <div class="admin-panel-heading">
                <div><p class="admin-page-eyebrow">Danh mục bán phòng</p><h2 class="mt-1 text-sm font-bold text-[#2c342e]">Loại phòng và giá theo ngày</h2></div>
                <span class="hotel-count-chip">{{ $types->total() }} loại phòng</span>
            </div>
            <div class="overflow-x-auto">
                <table class="admin-data-table hotel-room-table">
                    <thead><tr><th>Khách sạn / loại phòng</th><th>Sức chứa</th><th>Nguồn dữ liệu</th><th class="text-right">Quản lý</th></tr></thead>
                    <tbody>
                        @forelse($types as $type)
                            <tr>
                                <td><strong class="block text-[11px] font-semibold text-[#303832]">{{ $type->hotel->name }}</strong><small class="mt-1 block text-[9px] text-[#89928b]">{{ $type->name }}</small></td>
                                <td class="whitespace-nowrap text-[#657068]">{{ $type->max_guests }} khách / phòng</td>
                                <td>@if($type->is_demo)<span class="hotel-source-demo">Dữ liệu minh họa</span>@else<span class="hotel-source-live">Đang quản lý</span>@endif</td>
                                <td class="text-right"><a href="{{ route('admin.hotel-booking.inventory', $type) }}" class="hotel-manage-link">Tồn & giá <x-icon name="arrow" class="size-3.5" /></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-[11px] text-[#89928b]">Chưa có loại phòng. Thêm loại phòng để bắt đầu cấu hình giá và tồn.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 sm:px-6">{{ $types->links() }}</div>
        </div>

        <aside class="hotel-create-panel">
            <span class="hotel-create-icon"><x-icon name="plus" class="size-5" /></span>
            <p class="admin-page-eyebrow mt-4">Cấu hình sản phẩm</p>
            <h2 class="mt-1 text-base font-bold text-[#29312c]">Thêm loại phòng</h2>
            <p class="mt-1 text-[10px] leading-5 text-[#78837b]">Sau khi tạo, nhập tồn phòng và giá cho từng đêm trước khi mở bán.</p>
            <form method="POST" action="{{ route('admin.hotel-booking.room-types.store') }}" class="mt-5 grid gap-3">
                @csrf
                <label class="hotel-form-label">Khách sạn
                    <select class="hotel-form-control" name="hotel_id" required>
                        @foreach($hotels as $hotel)<option value="{{ $hotel->id }}">{{ $hotel->name }}</option>@endforeach
                    </select>
                </label>
                <label class="hotel-form-label">Tên loại phòng
                    <input class="hotel-form-control" name="name" maxlength="120" placeholder="Ví dụ: Phòng Superior 2 giường" required>
                </label>
                <label class="hotel-form-label">Sức chứa tối đa
                    <span class="relative block"><input class="hotel-form-control pr-16" type="number" name="max_guests" min="1" max="20" value="2" required><span class="hotel-input-suffix">khách/phòng</span></span>
                </label>
                <label class="hotel-demo-toggle"><input type="checkbox" name="is_demo" value="1"><span><strong>Dữ liệu minh họa</strong><small>Đánh dấu giá và tồn phòng mẫu.</small></span></label>
                <button class="mt-1 inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-[#28745a] px-4 text-[10px] font-semibold text-white transition hover:bg-[#205d48]"><x-icon name="plus" class="size-4" /> Thêm loại phòng</button>
            </form>
        </aside>
    </section>

    <section id="hotel-bookings" class="admin-panel mt-7 scroll-mt-24">
        <div class="admin-panel-heading">
            <div><p class="admin-page-eyebrow">Đơn từ khách hàng</p><h2 class="mt-1 text-sm font-bold text-[#2c342e]">Đơn lưu trú gần đây</h2></div>
            <span class="hotel-count-chip">{{ $bookings->total() }} đơn</span>
        </div>
        <div class="overflow-x-auto">
            <table class="admin-data-table hotel-booking-table">
                <thead><tr><th>Mã đơn / khách hàng</th><th>Khách sạn / loại phòng</th><th>Thời gian lưu trú</th><th>Phòng</th><th class="text-right">Tổng tiền</th><th>Đơn / thanh toán</th><th></th></tr></thead>
                <tbody>
                    @forelse($bookings as $booking)
                        @php([$statusLabel, $statusClass] = $bookingStatuses[$booking->status] ?? [$booking->status, 'hotel-status-expired'])
                        <tr>
                            <td><strong class="block font-semibold text-[#28745a]">{{ $booking->code }}</strong><small class="mt-1 block text-[9px] text-[#78837b]">{{ $booking->customer->full_name }}</small><small class="mt-0.5 block text-[9px] text-[#89928b]">{{ $booking->customer->email }}</small></td>
                            <td><strong class="block max-w-[190px] truncate font-medium text-[#303832]">{{ $booking->roomType->hotel->name }}</strong><small class="mt-1 block text-[9px] text-[#89928b]">{{ $booking->roomType->name }}</small></td>
                            <td class="whitespace-nowrap text-[#657068]">{{ $booking->check_in->format('d/m/Y') }}<small class="mt-1 block text-[9px] text-[#89928b]">đến {{ $booking->check_out->format('d/m/Y') }}</small></td>
                            <td class="whitespace-nowrap text-[#657068]">{{ $booking->rooms }} phòng<small class="mt-1 block text-[9px] text-[#89928b]">{{ $booking->guests }} khách</small></td>
                            <td class="whitespace-nowrap text-right font-semibold text-[#303832]">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</td>
                            <td><span class="hotel-booking-status {{ $statusClass }}">{{ $statusLabel }}</span><small class="mt-1.5 block text-[9px] text-[#89928b]">{{ $booking->payment ? ($paymentStatuses[$booking->payment->status] ?? $booking->payment->status) : 'Chưa có thanh toán' }}</small></td>
                            <td class="text-right">
                                @if($booking->status === 'confirmed' && !$booking->check_out->isFuture())
                                    <form method="POST" action="{{ route('admin.hotel-booking.complete', $booking) }}" onsubmit="return confirm('Xác nhận hoàn thành lưu trú cho đơn {{ $booking->code }}?')">
                                        @csrf<button class="hotel-complete-button">Hoàn tất</button>
                                    </form>
                                @else
                                    <span class="text-[9px] text-[#a0a8a1]">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-14 text-center"><span class="mx-auto grid size-11 place-items-center rounded-xl bg-[#f2f5f2] text-[#879188]"><x-icon name="bed" /></span><p class="mt-3 text-[11px] font-semibold text-[#4b554d]">Chưa có đơn lưu trú</p><p class="mt-1 text-[10px] text-[#89928b]">Đơn đặt phòng của khách sẽ xuất hiện tại đây.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 sm:px-6">{{ $bookings->links() }}</div>
    </section>
@endsection
