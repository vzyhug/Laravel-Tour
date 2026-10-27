@extends('layouts.admin')

@section('title', 'Đơn ' . $booking->booking_code)

@php
    $labels = ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'];
    $actions = ['confirmed' => 'Xác nhận đơn', 'completed' => 'Hoàn thành đơn'];
    $departure = $booking->departure;
@endphp

@section('content')
    <a class="font-semibold text-brand-700" href="{{ route('admin.bookings.index') }}">← Danh sách đơn</a>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-black text-ink">Đơn {{ $booking->booking_code }}</h1>
        <span class="badge">{{ $labels[$booking->status] ?? $booking->status }}</span>
    </div>

    <div class="mt-7 grid gap-5 md:grid-cols-2">
        <div class="card p-6">
            <h2 class="font-bold text-ink">Khách hàng</h2>
            <p class="mt-3">{{ $booking->customer->full_name }}</p>
            <p class="text-sm text-slate-500">{{ $booking->customer->email }}</p>
            <p class="text-sm text-slate-500">{{ $booking->customer->phone }}</p>
        </div>

        <div class="card p-6">
            <h2 class="font-bold text-ink">Chuyến đi</h2>
            <p class="mt-3">{{ $departure->tour->title }}</p>
            <p class="text-sm text-slate-500">
                Khởi hành {{ $departure->departure_date->format('d/m/Y') }}@if($departure->time_label) · {{ $departure->time_label }}@endif
                – về {{ $departure->return_date->format('d/m/Y') }}
            </p>
            <p class="text-sm text-slate-500">{{ $booking->total_adults + $booking->total_children }} hành khách</p>
        </div>

        <div class="card p-6">
            <h2 class="font-bold text-ink">Cách tham gia</h2>
            @if($booking->pickup_type === 'door')
                <p class="mt-3 font-semibold">Đón tại địa chỉ khách</p>
                <p class="text-sm text-slate-600">{{ $booking->pickup_address }}</p>
                @if($booking->pickup_note)
                    <p class="text-sm text-slate-500">Ghi chú: {{ $booking->pickup_note }}</p>
                @endif
            @else
                <p class="mt-3 font-semibold">Khách tự đến điểm tập trung</p>
                <p class="text-sm text-slate-600">{{ $departure->tour->pickup_point ?: 'Chưa có điểm tập trung' }}</p>
            @endif
        </div>

        <div class="card p-6">
            <h2 class="font-bold text-ink">Thanh toán</h2>
            <p class="mt-3 text-2xl font-black">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</p>
            @if($booking->discount_amount > 0)
                <p class="text-sm text-slate-500">Đã dùng mã {{ $booking->coupon?->code }}, giảm {{ number_format($booking->discount_amount, 0, ',', '.') }}₫</p>
            @endif
            @if($booking->payment)
                <p class="text-sm text-slate-500">
                    {{ str_replace('_', ' ', $booking->payment->payment_method) }} · {{ $booking->payment->status }}
                </p>
            @else
                <p class="text-sm text-slate-500">Chưa có giao dịch thanh toán.</p>
            @endif
        </div>
    </div>

    @if($booking->status === 'cancelled')
        <div class="card mt-5 border border-red-200 p-6">
            <h2 class="font-bold text-red-600">Khách đã hủy đơn này</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Hủy lúc</dt>
                    <dd class="font-semibold">{{ $booking->cancelled_at?->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Lý do</dt>
                    <dd class="text-right font-semibold">{{ $booking->cancel_reason }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Hoàn tiền</dt>
                    <dd class="font-semibold">
                        @if($booking->refund_amount !== null)
                            {{ $booking->refund_percent }}% · {{ number_format($booking->refund_amount, 0, ',', '.') }}₫
                        @else
                            Không có (đơn chưa thanh toán)
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    @else
        <div class="card mt-5 p-6">
            <h2 class="font-bold text-ink">Cập nhật trạng thái</h2>

            @error('status')
                <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if(count($nextStatuses))
                <div class="mt-3 flex flex-wrap gap-3">
                    @foreach($nextStatuses as $status)
                        <form method="POST" action="{{ route('admin.bookings.status', $booking) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $status }}">
                            <button class="btn-primary">{{ $actions[$status] }}</button>
                        </form>
                    @endforeach
                </div>
            @else
                <p class="mt-3 text-sm text-slate-500">Đơn đã hoàn thành, không thể thay đổi.</p>
            @endif
            <p class="mt-3 text-xs text-slate-500">Việc hủy đơn do khách tự thực hiện theo chính sách hủy tour, admin không hủy thay khách.</p>
        </div>
    @endif

    <div class="card mt-5 overflow-hidden">
        <h2 class="p-6 pb-3 font-bold text-ink">Danh sách hành khách</h2>
        <div class="overflow-x-auto">
            <table class="w-full min-w-150 text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="p-4">Họ tên</th>
                        <th class="p-4">CCCD</th>
                        <th class="p-4">Ngày sinh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($booking->passengers as $p)
                        <tr>
                            <td class="p-4">{{ $p->full_name }}</td>
                            <td class="p-4">{{ $p->id_card ?: '—' }}</td>
                            <td class="p-4">{{ $p->date_of_birth ? \Carbon\Carbon::parse($p->date_of_birth)->format('d/m/Y') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
