@extends('layouts.admin')

@section('title', 'Đơn đặt tour')

@php
    $labels = ['pending' => 'Chờ thanh toán', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'];
@endphp

@section('content')
    <div>
        <p class="font-semibold text-brand-700">Bán hàng</p>
        <h1 class="text-3xl font-black text-ink">Đơn đặt tour</h1>
    </div>

    <form method="GET" class="sticky-search card mt-8 flex flex-wrap items-end gap-4 p-4">
        <label class="grow">
            <span class="label">Tìm kiếm</span>
            <input class="field" name="q" value="{{ request('q') }}" placeholder="Mã đơn, tên hoặc email khách">
        </label>
        <label>
            <span class="label">Trạng thái</span>
            <select class="field" name="status">
                <option value="">Tất cả</option>
                @foreach($labels as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-primary">Lọc</button>
    </form>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-225 text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="p-4">Mã đơn</th>
                        <th class="p-4">Khách hàng</th>
                        <th class="p-4">Tour</th>
                        <th class="p-4">Khởi hành</th>
                        <th class="p-4">Tổng tiền</th>
                        <th class="p-4">Trạng thái</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $booking)
                        <tr>
                            <td class="p-4">
                                <strong>{{ $booking->booking_code }}</strong>
                                <p class="text-xs text-slate-500">{{ $booking->booking_date->format('d/m/Y H:i') }}</p>
                            </td>
                            <td class="p-4">{{ $booking->customer->full_name }}</td>
                            <td class="p-4">{{ $booking->departure->tour->title }}</td>
                            <td class="p-4">{{ $booking->departure->departure_date->format('d/m/Y') }}</td>
                            <td class="p-4 font-bold">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</td>
                            <td class="p-4"><span class="badge">{{ $labels[$booking->status] ?? $booking->status }}</span></td>
                            <td class="p-4">
                                <a class="font-semibold text-brand-700 hover:underline"
                                   href="{{ route('admin.bookings.show', $booking) }}">Chi tiết</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-slate-500">Không có đơn nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $bookings->links() }}</div>
@endsection
