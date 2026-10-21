@extends('layouts.admin')

@section('title', $coupon->exists ? 'Sửa mã giảm giá' : 'Thêm mã giảm giá')

@php
    $money = function (string $field) use ($coupon) {
        $value = old($field, $coupon->$field);

        return ($value === null || $value === '' || (float) $value == 0 && $field === 'max_discount')
            ? ''
            : number_format((float) $value, 0, ',', '.');
    };
@endphp

@section('content')
    <a class="font-semibold text-brand-700" href="{{ route('admin.coupons.index') }}">← Mã giảm giá</a>

    <h1 class="mt-3 text-3xl font-black text-ink">
        {{ $coupon->exists ? 'Chỉnh sửa mã giảm giá' : 'Thêm mã giảm giá' }}
    </h1>

    <form class="card mt-7 grid gap-5 p-6 md:grid-cols-2" method="POST"
          action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}">
        @csrf
        @if($coupon->exists)
            @method('PUT')
        @endif

        <label>
            <span class="label">Mã giảm giá</span>
            <input class="field" name="code" value="{{ old('code', $coupon->code) }}"
                   placeholder="VIETGO10" style="text-transform:uppercase;" required>
            @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </label>

        <label>
            <span class="label">Phần trăm giảm (%)</span>
            <input class="field" type="number" name="discount_percent" min="1" max="100"
                   value="{{ old('discount_percent', $coupon->discount_percent) }}" required>
            @error('discount_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </label>

        <label>
            <span class="label">Giảm tối đa (₫) <span class="font-normal text-slate-500">- để trống nếu không giới hạn</span></span>
            <input class="field" type="text" name="max_discount" data-money-mask inputmode="numeric"
                   autocomplete="off" placeholder="500.000" value="{{ $money('max_discount') }}">
            @error('max_discount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </label>

        <label>
            <span class="label">Đơn tối thiểu (₫)</span>
            <input class="field" type="text" name="min_order" data-money-mask inputmode="numeric"
                   autocomplete="off" placeholder="2.000.000" value="{{ $money('min_order') }}">
            @error('min_order')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </label>

        <x-date-input name="starts_on" label="Ngày bắt đầu (không bắt buộc)"
                      :value="$coupon->starts_on?->format('Y-m-d')" />

        <x-date-input name="expires_on" label="Ngày hết hạn (không bắt buộc)"
                      :value="$coupon->expires_on?->format('Y-m-d')" />

        <label>
            <span class="label">Số lượt dùng tối đa <span class="font-normal text-slate-500">- để trống nếu không giới hạn</span></span>
            <input class="field" type="number" name="usage_limit" min="1"
                   value="{{ old('usage_limit', $coupon->usage_limit) }}">
            @error('usage_limit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </label>

        <label class="flex items-center gap-3 self-end rounded-xl bg-slate-50 p-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active))>
            <span class="font-semibold">Đang bật</span>
        </label>

        <div class="md:col-span-2">
            <button class="btn-primary">Lưu mã giảm giá</button>
        </div>
    </form>

    <script>
        document.querySelectorAll('[data-money-mask]').forEach(input => {
            input.addEventListener('input', () => {
                const d = input.value.replace(/\D/g, '');
                input.value = d.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            });
        });
    </script>
@endsection
