@extends('layouts.admin')

@section('title', 'Mã giảm giá')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-semibold text-brand-700">Bán hàng</p>
            <h1 class="text-3xl font-black text-ink">Mã giảm giá</h1>
        </div>
        <a class="btn-primary" href="{{ route('admin.coupons.create') }}">+ Thêm mã</a>
    </div>

    <div class="card mt-8 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-225 text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="p-4">Mã</th>
                        <th class="p-4">Giảm</th>
                        <th class="p-4">Đơn tối thiểu</th>
                        <th class="p-4">Thời hạn</th>
                        <th class="p-4">Đã dùng</th>
                        <th class="p-4">Trạng thái</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($coupons as $coupon)
                        <tr>
                            <td class="p-4 font-bold">{{ $coupon->code }}</td>
                            <td class="p-4">
                                {{ $coupon->discount_percent }}%
                                @if($coupon->max_discount !== null && (float) $coupon->max_discount > 0)
                                    <p class="text-xs text-slate-500">Tối đa {{ number_format($coupon->max_discount, 0, ',', '.') }}₫</p>
                                @endif
                            </td>
                            <td class="p-4">{{ number_format($coupon->min_order, 0, ',', '.') }}₫</td>
                            <td class="p-4">
                                {{ $coupon->starts_on?->format('d/m/Y') ?? '—' }} → {{ $coupon->expires_on?->format('d/m/Y') ?? 'Không giới hạn' }}
                            </td>
                            <td class="p-4">{{ $coupon->used_count }}{{ $coupon->usage_limit ? '/'.$coupon->usage_limit : '' }}</td>
                            <td class="p-4"><span class="badge">{{ $coupon->is_active ? 'Đang bật' : 'Đã tắt' }}</span></td>
                            <td class="p-4">
                                <a class="font-semibold text-brand-700 hover:underline"
                                   href="{{ route('admin.coupons.edit', $coupon) }}">Sửa</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-slate-500">Chưa có mã giảm giá.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $coupons->links() }}</div>
@endsection
