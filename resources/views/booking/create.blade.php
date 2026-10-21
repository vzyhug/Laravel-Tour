@extends('layouts.app')

@section('title', 'Đặt tour · ' . $departure->tour->title)

@section('content')
<section class="container-page py-10">
    <div class="mb-8">
        <p class="font-semibold text-brand-700">Bước 1 / 2</p>
        <h1 class="mt-1 text-3xl font-black text-ink">Thông tin hành khách</h1>
    </div>

    <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
        {{-- Form đặt tour --}}
        <form class="card p-6" method="POST" action="{{ route('bookings.store') }}" id="booking-form">
            @csrf
            <input type="hidden" name="departure_id" value="{{ $departure->getKey() }}">

            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">Danh sách hành khách</h2>
                <button type="button" id="add-passenger" class="btn-secondary">+ Thêm người</button>
            </div>

            <div id="passengers" class="mt-6 grid gap-5"></div>

            {{-- Cách tham gia chuyến đi --}}
            <div class="mt-8 border-t border-slate-200 pt-6">
                <h2 class="text-xl font-bold">Cách tham gia chuyến đi</h2>

                <div class="mt-4 grid gap-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4">
                        <input type="radio" name="pickup_type" value="self" class="mt-1"
                               @checked(old('pickup_type', 'self') === 'self')>
                        <span>
                            <strong>Tự đến điểm tập trung</strong>
                            <span class="block text-sm text-slate-500">
                                {{ $departure->tour->pickup_point ?: 'Điểm tập trung sẽ được thông báo sau' }}@if($departure->time_label) · {{ $departure->time_label }}@endif
                            </span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4">
                        <input type="radio" name="pickup_type" value="door" class="mt-1"
                               @checked(old('pickup_type') === 'door')>
                        <span>
                            <strong>Đón tại địa chỉ của tôi</strong>
                            <span class="block text-sm text-slate-500">Nhân viên sẽ liên hệ xác nhận giờ đón.</span>
                        </span>
                    </label>
                </div>

                <div id="door-fields" class="mt-4 grid gap-4 {{ old('pickup_type') === 'door' ? '' : 'hidden' }}">
                    <label>
                        <span class="label">Địa chỉ đón</span>
                        <input class="field" name="pickup_address" value="{{ old('pickup_address') }}">
                    </label>
                    <label>
                        <span class="label">Ghi chú cho tài xế (không bắt buộc)</span>
                        <input class="field" name="pickup_note" value="{{ old('pickup_note') }}">
                    </label>
                </div>

                @error('pickup_address')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

                        {{-- Mã giảm giá --}}
            <div class="mt-8 border-t border-slate-200 pt-6">
                <h2 class="text-xl font-bold">Mã giảm giá</h2>

                @if ($coupons->isNotEmpty())
                    <div class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.75rem">
                        @foreach ($coupons as $coupon)
                            <button type="button"
                                    onclick="document.querySelector('#coupon-code').value = '{{ $coupon->code }}'; document.querySelector('#apply-coupon').click();"
                                    class="rounded-xl border border-dashed border-brand-500 bg-brand-50 p-4 text-left hover:bg-brand-100">
                                <strong class="block text-brand-700">{{ $coupon->code }}</strong>
                                <span class="mt-1 block text-sm font-semibold text-ink">Giảm {{ rtrim(rtrim(number_format((float) $coupon->discount_percent, 2, '.', ''), '0'), '.') }}%@if ($coupon->max_discount && (float) $coupon->max_discount > 0), tối đa {{ number_format((float) $coupon->max_discount, 0, ',', '.') }}₫@endif</span>
                                <span class="mt-1 block text-xs text-slate-500">
                                    @if ((float) $coupon->min_order > 0)Đơn từ {{ number_format((float) $coupon->min_order, 0, ',', '.') }}₫ · @endif
                                    @if ($coupon->expires_on)HSD {{ $coupon->expires_on->format('d/m/Y') }}@else Không giới hạn thời gian @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4" style="display:flex;gap:.75rem;align-items:stretch">
                    <input class="field" style="flex:1;min-width:0;text-transform:uppercase" id="coupon-code" name="coupon_code"
                           value="{{ old('coupon_code') }}" placeholder="Hoặc nhập mã giảm giá khác" autocomplete="off">
                    <button type="button" id="apply-coupon" class="btn-secondary" style="white-space:nowrap">Áp dụng</button>
                </div>
                <p id="coupon-message" class="mt-2 text-sm"></p>
                <div id="coupon-summary" class="mt-3 hidden rounded-xl bg-slate-50 p-4 text-sm"></div>
                @error('coupon_code')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button class="btn-primary mt-7 w-full">Tiếp tục thanh toán</button>
        </form>

        {{-- Thông tin chuyến đi --}}
        <aside class="card h-fit p-6">
            <p class="text-sm font-semibold text-brand-700">{{ $departure->tour->title }}</p>

            <dl class="mt-5 grid gap-4 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Khởi hành</dt>
                    <dd class="font-bold">{{ $departure->departure_date->format('d/m/Y') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Người lớn</dt>
                    <dd class="font-bold">{{ number_format($departure->adult_price, 0, ',', '.') }}₫</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Trẻ em</dt>
                    <dd class="font-bold">{{ number_format($departure->child_price, 0, ',', '.') }}₫</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Chỗ còn lại</dt>
                    <dd class="font-bold text-brand-700">{{ $departure->available_seats }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Giờ tập trung</dt>
                    <dd class="font-bold">{{ $departure->time_label ?? 'Sẽ thông báo' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Điểm tập trung</dt>
                    <dd class="text-right font-bold">{{ $departure->tour->pickup_point ?: 'Sẽ thông báo' }}</dd>
                </div>
            </dl>

            <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                <p class="font-bold text-ink">Chính sách hủy tour</p>
                <p class="mt-2">
                    Được hủy khi còn cách ngày khởi hành từ {{ config('booking.cancel_before_days') }} ngày trở lên.
                </p>
                <p class="mt-1">
                    Hủy trong {{ config('booking.full_refund_hours') }} giờ sau khi đặt: hoàn 100%.
                    Sau đó: hoàn {{ config('booking.partial_refund_percent') }}%.
                </p>
            </div>
        </aside>
    </div>
</section>

@push('scripts')
<template id="passenger-template">
    <fieldset class="relative grid gap-4 rounded-2xl border border-slate-200 p-5 sm:grid-cols-2">
        <legend class="px-2 font-bold">Hành khách <span data-number></span></legend>
        <button type="button" data-remove class="absolute right-4 top-3 text-sm font-semibold text-red-600">Xóa</button>

        <label class="sm:col-span-2">
            <span class="label">Họ và tên</span>
            <input class="field" data-name="full_name" required>
        </label>

        <label>
            <span class="label">Loại hành khách</span>
            <select class="field" data-name="passenger_type">
                <option value="adult">Người lớn</option>
                <option value="child">Trẻ em</option>
            </select>
        </label>

        <label>
            <span class="label">Ngày sinh</span>
            <input class="field" type="date" data-name="date_of_birth">
        </label>

        <label class="sm:col-span-2">
            <span class="label">CCCD / Hộ chiếu</span>
            <input class="field" data-name="id_card">
        </label>
    </fieldset>
</template>

<script>
    const list = document.querySelector('#passengers');
    const template = document.querySelector('#passenger-template');
    const oldPassengers = Object.values(@json(old('passengers', [])));

    function renumber() {
        [...list.children].forEach((row, i) => {
            row.querySelector('[data-number]').textContent = i + 1;
            row.querySelectorAll('[data-name]').forEach(input => {
                input.name = `passengers[${i}][${input.dataset.name}]`;
            });
            row.querySelector('[data-remove]').classList.toggle('hidden', list.children.length === 1);
        });
    }

    function add(data = {}) {
        list.append(template.content.cloneNode(true));
        const row = list.lastElementChild;
        row.querySelectorAll('[data-name]').forEach(input => {
            const value = data[input.dataset.name];
            if (value !== undefined && value !== null) {
                input.value = value;
            }
        });
        renumber();
    }

    document.querySelector('#add-passenger').addEventListener('click', () => add());

    list.addEventListener('click', e => {
        if (e.target.matches('[data-remove]')) {
            e.target.closest('fieldset').remove();
            renumber();
        }
    });

    // Khôi phục hành khách đã nhập nếu form bị báo lỗi, không có thì tạo 1 dòng trống
    if (oldPassengers.length) {
        oldPassengers.forEach(p => add(p));
    } else {
        add();
    }

    // Hiện/ẩn ô địa chỉ đón
    const doorFields = document.querySelector('#door-fields');
    document.querySelectorAll('input[name="pickup_type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const type = document.querySelector('input[name="pickup_type"]:checked').value;
            doorFields.classList.toggle('hidden', type !== 'door');
        });
    });

    // ===== Mã giảm giá =====
    const couponInput = document.querySelector('#coupon-code');
    const couponMessage = document.querySelector('#coupon-message');
    const couponSummary = document.querySelector('#coupon-summary');
    const formatMoney = n => Number(n).toLocaleString('vi-VN') + '₫';

    function resetCouponView() {
        couponMessage.textContent = '';
        couponSummary.classList.add('hidden');
    }

    async function checkCoupon() {
        const code = couponInput.value.trim();
        if (!code) {
            resetCouponView();
            return;
        }

        const types = [...document.querySelectorAll('[data-name="passenger_type"]')].map(s => s.value);

        try {
            const response = await fetch(@json(route('coupons.check')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('#booking-form input[name="_token"]').value,
                },
                body: JSON.stringify({
                    departure_id: document.querySelector('#booking-form input[name="departure_id"]').value,
                    adults: types.filter(t => t === 'adult').length,
                    children: types.filter(t => t === 'child').length,
                    code,
                }),
            });
            const result = await response.json();

            if (!response.ok || !result.valid) {
                couponMessage.style.color = '#dc2626';
                couponMessage.textContent = result.message || 'Không kiểm tra được mã giảm giá.';
                couponSummary.classList.add('hidden');
                return;
            }

            couponMessage.style.color = '#15803d';
            couponMessage.textContent = result.message;
            couponSummary.innerHTML =
                `<div class="flex justify-between"><span>Tạm tính</span><span>${formatMoney(result.subtotal)}</span></div>` +
                `<div class="mt-1 flex justify-between"><span>Giảm giá</span><span>-${formatMoney(result.discount)}</span></div>` +
                `<div class="mt-2 flex justify-between border-t border-slate-200 pt-2 font-bold"><span>Thành tiền</span><span>${formatMoney(result.total)}</span></div>`;
            couponSummary.classList.remove('hidden');
        } catch (e) {
            couponMessage.style.color = '#dc2626';
            couponMessage.textContent = 'Không kiểm tra được mã giảm giá, vui lòng thử lại.';
        }
    }

    document.querySelector('#apply-coupon').addEventListener('click', checkCoupon);

    couponInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            checkCoupon();
        }
    });

    // Đổi số hành khách hoặc loại hành khách thì phải bấm Áp dụng lại
    list.addEventListener('change', resetCouponView);
    document.querySelector('#add-passenger').addEventListener('click', resetCouponView);
    list.addEventListener('click', e => { if (e.target.matches('[data-remove]')) resetCouponView(); });

    if (couponInput.value.trim()) {
        checkCoupon();
    }
</script>
@endpush
@endsection
