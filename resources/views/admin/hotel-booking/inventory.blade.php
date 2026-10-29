@extends('layouts.admin')
@section('title','Tồn và giá phòng')
@section('content')

<section class="space-y-6"><a class="font-semibold text-brand-700" href="{{ route('admin.hotel-booking.index') }}">← Quản trị khách sạn</a><div><p class="font-semibold text-brand-700">Khách sạn</p><h1 class="text-3xl font-black text-ink">{{ $type->hotel->name }} · {{ $type->name }}</h1></div>
@if($type->is_demo)<p>DỮ LIỆU DEMO — giá và tồn phòng minh họa.</p>@endif
<p>Nhập theo từng đêm; ngày trả phòng không chiếm tồn. Ngày chưa nhập không mở bán.</p>
<form method="POST" action="{{ route('admin.hotel-booking.inventory.save', $type) }}" class="grid gap-3 sm:grid-cols-3">@csrf @method('PUT')
<label>Đêm lưu trú<input class="field" type="date" name="stay_date" min="{{ today()->toDateString() }}" required></label>
<label>Tổng phòng<input class="field" type="number" name="total_rooms" min="0" max="10000" required></label>
<label>Giá VND/phòng/đêm<input class="field" type="text" inputmode="numeric" data-money name="nightly_price" required></label>
<button class="btn-primary">Lưu tồn và giá</button></form>
<form method="GET" action="{{ route('admin.hotel-booking.inventory', $type) }}" class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4"><label><span class="label">Đêm lưu trú (từ ngày)</span><input class="field" type="date" name="from" value="{{ request('from') }}"></label><label><span class="label">Phòng trống</span><select class="field" name="availability"><option value="">Tất cả</option><option value="available" @selected(request('availability') === 'available')>Còn phòng</option><option value="full" @selected(request('availability') === 'full')>Hết phòng</option></select></label><label><span class="label">Giá từ (₫)</span><input class="field" type="text" inputmode="numeric" data-money name="min_price" value="{{ request('min_price') }}"></label><label><span class="label">Giá đến (₫)</span><input class="field" type="text" inputmode="numeric" data-money name="max_price" value="{{ request('max_price') }}"></label><div class="flex gap-3 lg:col-span-4"><button class="btn-primary">Lọc</button><a class="inline-flex items-center rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-100" href="{{ route('admin.hotel-booking.inventory', $type) }}">Xóa lọc</a></div></form>
<div class="card overflow-hidden"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-4">Đêm lưu trú</th><th class="p-4">Phòng trống</th><th class="p-4 text-right">Giá/phòng/đêm</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($nights as $night)<tr><td class="p-4 font-semibold">{{ $night->stay_date->format('d/m/Y') }}</td><td class="p-4">{{ $night->available_rooms }}/{{ $night->total_rooms }}</td><td class="p-4 text-right">{{ number_format($night->nightly_price) }} ₫</td></tr>@endforeach</tbody></table></div>
{{ $nights->withQueryString()->links() }}</section>
<script>
document.querySelectorAll('[data-money]').forEach(function (input) {
    const format = () => {
        const digits = input.value.replace(/\D/g, '');
        input.value = digits ? Number(digits).toLocaleString('vi-VN') : '';
    };
    format();
    input.addEventListener('input', format);
    input.form.addEventListener('submit', () => {
        input.value = input.value.replace(/\D/g, '');
    });
});
</script>
@endsection
