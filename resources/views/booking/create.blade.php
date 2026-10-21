@extends('layouts.app')
@section('title', 'Đặt tour · '.$departure->tour->title)
@section('content')
<section class="container-page py-10"><div class="mb-8"><p class="font-semibold text-brand-700">Bước 1 / 2</p><h1 class="mt-1 text-3xl font-black text-ink">Thông tin hành khách</h1></div>
<div class="grid gap-8 lg:grid-cols-[1fr_360px]">
<form class="card p-6" method="POST" action="{{ route('bookings.store') }}" id="booking-form">@csrf<input type="hidden" name="departure_id" value="{{ $departure->getKey() }}"><div class="flex items-center justify-between"><h2 class="text-xl font-bold">Danh sách hành khách</h2><button type="button" id="add-passenger" class="btn-secondary">+ Thêm người</button></div><div id="passengers" class="mt-6 grid gap-5"></div><button class="btn-primary mt-7 w-full">Tiếp tục thanh toán</button></form>
<aside class="card h-fit p-6"><p class="text-sm font-semibold text-brand-700">{{ $departure->tour->title }}</p><dl class="mt-5 grid gap-4 text-sm"><div class="flex justify-between gap-4"><dt class="text-slate-500">Khởi hành</dt><dd class="font-bold">{{ $departure->departure_date->format('d/m/Y') }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Người lớn</dt><dd class="font-bold">{{ number_format($departure->adult_price, 0, ',', '.') }}₫</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Trẻ em</dt><dd class="font-bold">{{ number_format($departure->child_price, 0, ',', '.') }}₫</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Chỗ còn lại</dt><dd class="font-bold text-brand-700">{{ $departure->available_seats }}</dd></div></dl></aside>
</div></section>
@push('scripts')
<template id="passenger-template"><fieldset class="relative grid gap-4 rounded-2xl border border-slate-200 p-5 sm:grid-cols-2"><legend class="px-2 font-bold">Hành khách <span data-number></span></legend><button type="button" data-remove class="absolute right-4 top-3 text-sm font-semibold text-red-600">Xóa</button><label class="sm:col-span-2"><span class="label">Họ và tên</span><input class="field" data-name="full_name" required></label><label><span class="label">Loại hành khách</span><select class="field" data-name="passenger_type"><option value="adult">Người lớn</option><option value="child">Trẻ em</option></select></label><label><span class="label">Ngày sinh</span><input class="field" type="date" data-name="date_of_birth"></label><label class="sm:col-span-2"><span class="label">CCCD / Hộ chiếu</span><input class="field" data-name="id_card"></label></fieldset></template>
<script>
const list = document.querySelector('#passengers');
const template = document.querySelector('#passenger-template');
const maxSeats = {{ $departure->available_seats }};
const initialPassengers = @json(old('passengers', []));

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
    if (list.children.length >= maxSeats) {
        alert('Số lượng hành khách không được vượt quá số chỗ còn lại (' + maxSeats + ' chỗ).');
        return;
    }
    const node = template.content.cloneNode(true);
    if (data.full_name) node.querySelector('[data-name="full_name"]').value = data.full_name;
    if (data.passenger_type) node.querySelector('[data-name="passenger_type"]').value = data.passenger_type;
    if (data.date_of_birth) node.querySelector('[data-name="date_of_birth"]').value = data.date_of_birth;
    if (data.id_card) node.querySelector('[data-name="id_card"]').value = data.id_card;
    list.append(node);
    renumber();
}

document.querySelector('#add-passenger').addEventListener('click', () => add());

list.addEventListener('click', e => {
    if (e.target.matches('[data-remove]')) {
        e.target.closest('fieldset').remove();
        renumber();
    }
});

if (Array.isArray(initialPassengers) && initialPassengers.length > 0) {
    initialPassengers.forEach(p => add(p));
} else {
    add();
}
</script>
@endpush
@endsection
