@extends('layouts.app')
@section('title', 'Chọn phòng · '.$hotel->name)
@section('content')
@php($cover = $hotel->images->first())
<section class="container-page py-7 sm:py-10">
    <a class="text-link" href="{{ route('hotels.show', $hotel) }}">← {{ $hotel->name }}</a>
    <div class="hotel-search-hero mt-4">
        @if($cover)<img src="{{ $cover->url }}" alt="{{ $cover->caption }}" class="absolute inset-0 size-full object-cover">@endif
        <div class="absolute inset-0 bg-gradient-to-r from-[#211a17]/90 via-[#211a17]/65 to-[#211a17]/10"></div>
        <div class="relative z-10 max-w-2xl p-6 text-white sm:p-10 lg:p-12"><p class="eyebrow text-brand-100">{{ $hotel->destination->name }} · VietGo stay</p><h1 class="mt-3 font-serif text-4xl leading-tight sm:text-5xl">Nghỉ tại <em class="text-[#ffd9c4]">{{ $hotel->name }}</em></h1><p class="mt-4 line-clamp-3 text-sm leading-7 text-white/80">{{ $hotel->description }}</p><div class="mt-5 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-2 text-[10px] backdrop-blur"><x-icon name="check" class="size-4 text-[#c7e0ca]"/> Chọn ngày để xem giá và tình trạng phòng</div></div>
        @if($hotel->images->count() > 1)<span class="absolute bottom-5 right-5 z-10 rounded-full bg-black/45 px-3 py-2 text-[10px] text-white backdrop-blur"><x-icon name="map" class="mr-1 inline size-3.5"/> {{ $hotel->images->count() }} ảnh khách sạn</span>@endif
    </div>

    <form class="sticky-search hotel-stay-form relative z-10 -mt-6 grid gap-3 rounded-2xl border border-[#eadfd6] bg-white p-4 shadow-[0_18px_55px_rgba(58,40,25,.14)] md:grid-cols-[1fr_1fr_.8fr_.8fr_auto] md:items-end md:p-5" method="GET" data-hotel-search>
        <label><span class="label flex items-center gap-2"><x-icon name="calendar" class="size-4 text-brand-600"/> Nhận phòng</span><input class="field" type="date" name="check_in" min="{{ today()->toDateString() }}" required value="{{ $data['check_in'] ?? '' }}" data-hotel-check-in></label>
        <label><span class="label flex items-center gap-2"><x-icon name="calendar" class="size-4 text-brand-600"/> Trả phòng</span><input class="field" type="date" name="check_out" required value="{{ $data['check_out'] ?? '' }}" data-hotel-check-out></label>
        <label><span class="label flex items-center gap-2"><x-icon name="map" class="size-4 text-brand-600"/> Số phòng</span><select class="field" name="rooms">@for($count = 1; $count <= 10; $count++)<option value="{{ $count }}" @selected((int) $data['rooms'] === $count)>{{ $count }} phòng</option>@endfor</select></label>
        <label><span class="label flex items-center gap-2"><x-icon name="users" class="size-4 text-brand-600"/> Số khách</span><select class="field" name="guests">@for($count = 1; $count <= 100; $count++)<option value="{{ $count }}" @selected((int) $data['guests'] === $count)>{{ $count }} khách</option>@endfor</select></label>
        <button class="btn-primary min-h-12 w-full md:w-auto" data-hotel-search-submit><x-icon name="search" class="size-4"/> Tìm phòng</button>
        <p class="md:col-span-5 text-[10px] text-muted" aria-live="polite" data-hotel-stay-length>@if(!empty($data['check_in']) && !empty($data['check_out'])){{ \Carbon\Carbon::parse($data['check_in'])->diffInDays(\Carbon\Carbon::parse($data['check_out'])) }} đêm lưu trú · Giá tính theo từng đêm@else Chọn ngày nhận và trả phòng để xem giá và phòng còn trống.@endif</p>
    </form>

    @if(!empty($data['check_in']))
        <section class="mt-12" aria-labelledby="hotel-results-title">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[#e7dcd1] pb-5"><div><p class="eyebrow">Kết quả theo ngày bạn chọn</p><h2 id="hotel-results-title" class="mt-2 font-serif text-3xl sm:text-4xl">Các lựa chọn lưu trú</h2></div><a href="{{ route('hotels.show', $hotel) }}" class="text-link">Xem thông tin khách sạn <x-icon name="arrow" class="size-4"/></a></div>
            <div class="mt-6 grid gap-5">
                @forelse($offers as $type)
                    @php($nightlyFrom = $type->inventory->min('nightly_price'))
                    @php($totalPrice = $type->inventory->sum('nightly_price') * $data['rooms'])
                    @php($roomsLeft = $type->inventory->min('available_rooms'))
                    <article class="room-offer-card">
                        <div class="room-offer-icon"><x-icon name="map" class="size-7"/></div>
                        <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><span class="eyebrow">VietGo stay</span></div><h3 class="mt-2 font-serif text-2xl">{{ $type->name }}</h3><p class="mt-2 flex items-center gap-2 text-xs text-muted"><x-icon name="users" class="size-4"/> Tối đa {{ $type->max_guests }} khách/phòng <span class="text-[#ccb6a8]">·</span><span class="text-sage">{{ $roomsLeft }} phòng còn trống</span></p><details class="mt-4"><summary class="inline-flex min-h-10 cursor-pointer items-center gap-2 text-xs font-semibold text-brand-700">Xem giá từng đêm <x-icon name="chevron" class="size-4"/></summary><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($type->inventory as $night)<div class="flex items-center justify-between rounded-lg bg-sand px-3 py-2 text-xs"><span>{{ $night->stay_date->format('D, d/m') }}</span><strong>{{ number_format($night->nightly_price, 0, ',', '.') }}₫</strong></div>@endforeach</div></details></div>
                        <div class="room-offer-price"><span class="text-[10px] font-semibold uppercase tracking-[.15em] text-muted">Giá tham khảo / đêm</span><strong class="mt-1 font-serif text-3xl text-brand-700">{{ number_format($nightlyFrom, 0, ',', '.') }}₫</strong><span class="text-[10px] text-muted">/ phòng / đêm</span><div class="mt-4 border-t border-[#eee3da] pt-3 text-right"><span class="block text-[10px] text-muted">Tổng {{ $type->inventory->count() }} đêm · {{ $data['rooms'] }} phòng</span><strong class="mt-1 block text-lg">{{ number_format($totalPrice, 0, ',', '.') }}₫</strong></div>
                            @if(session('customer_id'))<form method="POST" action="{{ route('hotel-bookings.store') }}" class="mt-4">@csrf<input type="hidden" name="request_key" value="{{ $requestKey }}"><input type="hidden" name="room_type_id" value="{{ $type->id }}">@foreach(['check_in', 'check_out', 'rooms', 'guests'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $data[$field] }}">@endforeach<button class="btn-primary w-full">Giữ phòng 15 phút <x-icon name="arrow" class="size-4"/></button></form>@else<a class="btn-primary mt-4 w-full" href="{{ route('login') }}">Đăng nhập để đặt phòng</a>@endif
                        </div>
                    </article>
                @empty
                    <div class="hotel-empty-state"><span class="grid size-14 place-items-center rounded-full bg-white text-brand-600"><x-icon name="calendar" class="size-6"/></span><div><h3 class="font-serif text-2xl">Chưa có phòng cho toàn bộ ngày đã chọn</h3><p class="mt-2 text-sm leading-6 text-muted">Thử điều chỉnh ngày nhận/trả phòng hoặc số lượng phòng. Giá và tồn tham khảo được cập nhật theo từng đêm.</p></div></div>
                @endforelse
            </div>
        </section>
    @else
        <section class="mt-12 grid gap-5 md:grid-cols-3">@foreach([['01','Chọn ngày','Tìm giá và tình trạng theo cả kỳ nghỉ.','calendar'],['02','So sánh','Xem giá mỗi đêm và tổng chi phí minh bạch.','search'],['03','Giữ phòng','Đăng nhập để giữ lựa chọn trong 15 phút.','check']] as [$step,$title,$copy,$icon])<article class="hotel-process-step"><span class="hotel-process-number">{{ $step }}</span><span class="grid size-10 place-items-center rounded-full bg-white text-brand-600"><x-icon :name="$icon" class="size-5"/></span><h2 class="mt-4 font-serif text-xl">{{ $title }}</h2><p class="mt-2 text-xs leading-6 text-muted">{{ $copy }}</p></article>@endforeach</section>
    @endif

    @if(session('customer_id'))<div class="mt-10 border-t border-[#e7dcd1] pt-5"><a class="text-link" href="{{ route('hotel-bookings.index') }}">Đơn khách sạn của tôi <x-icon name="arrow" class="size-4"/></a></div>@endif
</section>
@endsection
