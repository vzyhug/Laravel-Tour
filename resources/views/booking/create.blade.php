@extends('layouts.app')
@section('title', 'Đặt hành trình · '.$departure->tour->title)
@section('content')
@php
    $savedPassengers = old('passengers');
    $savedPassengers = is_array($savedPassengers) && count($savedPassengers) > 0 ? array_slice($savedPassengers, 0, 20) : [['passenger_type' => 'adult']];
    $adultCount = collect($savedPassengers)->where('passenger_type', 'adult')->count();
    $childCount = collect($savedPassengers)->where('passenger_type', 'child')->count();
    $estimatedTotal = $adultCount * $departure->adult_price + $childCount * $departure->child_price;
@endphp
<div class="border-b border-[#e5d9cf] bg-linen/60">
    <div class="container-page flex flex-col justify-between gap-4 py-5 md:flex-row md:items-center"><p class="eyebrow text-muted">Thiết kế hành trình <span class="mx-2 text-muted/40">/</span> <span class="text-brand-600">VietGo Travel</span></p><nav class="flex flex-wrap items-center gap-3 text-[10px]" aria-label="Các bước đặt tour"><a href="{{ route('tours.show', $departure->tour) }}" class="flex items-center gap-2 text-sage"><span class="step-dot step-complete"><x-icon name="check" class="size-3.5"/></span>Chọn hành trình</a><span class="h-px w-5 bg-[#d8c9bd]"></span><span class="flex items-center gap-2 font-semibold" aria-current="step"><span class="step-dot step-current">2</span>Thông tin hành khách</span><span class="h-px w-5 bg-[#d8c9bd]"></span><span class="flex items-center gap-2 text-muted"><span class="step-dot">3</span>Thanh toán</span></nav></div>
</div>
<div class="container-page grid items-start gap-8 py-8 lg:grid-cols-[minmax(0,1fr)_340px] xl:gap-10">
    <div class="min-w-0">
        <article class="card grid overflow-hidden sm:grid-cols-[210px_1fr]"><div class="relative min-h-48 bg-linen">@if($departure->tour->thumbnail_url)<img src="{{ $departure->tour->thumbnail_url }}" alt="{{ $departure->tour->title }}" class="absolute inset-0 size-full object-cover">@endif<span class="badge absolute left-3 top-3 bg-sand">Hành trình chọn lọc</span></div><div class="p-6"><p class="eyebrow text-[9px] text-muted">Hành trình bạn đã chọn</p><h1 class="mt-2 font-serif text-2xl leading-snug">{{ $departure->tour->title }}</h1><div class="mt-5 flex flex-wrap gap-4 rounded-lg bg-sand p-3 text-[10px]"><span class="flex items-center gap-2"><x-icon name="calendar" class="size-4 text-brand-600"/>{{ $departure->departure_date->format('d/m') }} – {{ $departure->return_date->format('d/m/Y') }}</span><span class="flex items-center gap-2"><x-icon name="clock" class="size-4 text-brand-600"/>{{ $departure->tour->duration_days }} ngày · {{ $departure->tour->duration_nights }} đêm</span></div></div></article>
        <div class="mt-8 flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Bước 2 / Dành cho những người đồng hành</p><h2 class="mt-2 font-serif text-3xl">Thông tin hành khách.</h2></div><span class="rounded-full bg-[#d1e8da]/60 px-3 py-1.5 text-[10px] text-sage">Còn {{ $departure->available_seats }} chỗ</span></div>
        <p class="mt-3 text-xs leading-7 text-muted">Điền thông tin chính xác để VietGo chuẩn bị cho chuyến đi của bạn. Bạn có thể thêm người lớn hoặc trẻ em bên dưới.</p>
        <form method="POST" action="{{ route('bookings.store') }}" id="booking-form" class="mt-6" data-booking-form data-adult-price="{{ $departure->adult_price }}" data-child-price="{{ $departure->child_price }}" data-max-passengers="{{ min(20, $departure->available_seats) }}">
            @csrf
            <input type="hidden" name="departure_id" value="{{ $departure->getKey() }}">
            <div id="passengers" class="grid gap-5">@foreach($savedPassengers as $index => $passenger)<x-passenger-fields :index="$index" :passenger="is_array($passenger) ? $passenger : []"/>@endforeach</div>
            <button type="button" id="add-passenger" class="btn-secondary mt-5 w-full border-dashed"><x-icon name="plus" class="size-4"/> Thêm người đồng hành</button>
            <div class="mt-8 rounded-xl bg-linen/60 p-5"><h3 class="flex items-center gap-2 font-serif text-lg"><x-icon name="shield" class="size-5 text-sage"/> Thông tin được bảo mật</h3><p class="mt-2 text-xs leading-7 text-muted">Thông tin hành khách phục vụ việc đặt tour và chuẩn bị chuyến đi. Hãy kiểm tra họ tên, ngày sinh và giấy tờ trước khi tiếp tục.</p></div>
            <button class="btn-primary mt-6 w-full lg:hidden">Tiếp tục thanh toán <x-icon name="arrow" class="size-4"/></button>
        </form>
        <template id="passenger-template"><x-passenger-fields index="__INDEX__"/></template>
    </div>
    <aside class="lg:sticky lg:top-28">
        <div class="card overflow-hidden">
            <div class="border-b border-[#e5d9cf] bg-[#f9f2ed] p-6"><p class="eyebrow">Chi phí hành trình</p><h2 class="mt-2 font-serif text-2xl">Tóm tắt đặt tour.</h2></div>
            <div class="p-6"><dl class="grid gap-4 text-xs"><div class="flex justify-between gap-4"><dt class="text-muted">Khởi hành</dt><dd class="font-semibold">{{ $departure->departure_date->format('d/m/Y') }}</dd></div><div class="flex justify-between gap-4"><dt class="text-muted">Kết thúc</dt><dd class="font-semibold">{{ $departure->return_date->format('d/m/Y') }}</dd></div></dl><div class="my-5 border-t border-[#e5d9cf]"></div><div aria-live="polite" aria-atomic="true"><dl class="grid gap-5 text-xs"><div class="flex justify-between gap-3"><dt><span class="block font-semibold">Người lớn × <span data-adult-count>{{ $adultCount }}</span></span><span class="mt-1 block text-[10px] text-muted">{{ number_format($departure->adult_price, 0, ',', '.') }}₫ / người</span></dt><dd class="font-semibold" data-adult-total>{{ number_format($adultCount * $departure->adult_price, 0, ',', '.') }}₫</dd></div><div class="flex justify-between gap-3"><dt><span class="block font-semibold">Trẻ em × <span data-child-count>{{ $childCount }}</span></span><span class="mt-1 block text-[10px] text-muted">{{ number_format($departure->child_price, 0, ',', '.') }}₫ / người</span></dt><dd class="font-semibold" data-child-total>{{ number_format($childCount * $departure->child_price, 0, ',', '.') }}₫</dd></div></dl><div class="mt-6 rounded-lg bg-sand p-4"><p class="eyebrow text-[9px] text-muted">Tổng chi phí dự kiến</p><p class="mt-2 font-serif text-3xl text-brand-600" data-booking-total>{{ number_format($estimatedTotal, 0, ',', '.') }}₫</p><p class="mt-2 text-[10px] leading-5 text-muted">Theo số hành khách và lịch khởi hành đã chọn.</p></div></div><button type="submit" form="booking-form" class="btn-primary mt-5 w-full">Tiếp tục thanh toán <x-icon name="arrow" class="size-4"/></button><p class="mt-3 text-center text-[10px] leading-5 text-muted">Kiểm tra lại thông tin trước khi giữ chỗ.</p></div>
        </div>
        <div class="mt-5 grid gap-3 px-2 text-[10px] text-muted"><p class="flex items-center gap-2"><x-icon name="check" class="size-4 text-sage"/> Giá rõ ràng theo từng hành khách</p><p class="flex items-center gap-2"><x-icon name="shield" class="size-4 text-sage"/> Thông tin được gửi an toàn</p><a href="tel:19006868" class="flex items-center gap-2"><x-icon name="phone" class="size-4 text-sage"/> Cần hỗ trợ? 1900 6868</a></div>
    </aside>
</div>
@endsection
