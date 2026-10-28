@extends('layouts.app')
@section('title', $record->name.' · VietGo')
@section('content')
<section class="container-page py-7 sm:py-10">
    <a href="{{ route($kind.'.index') }}" class="text-link">← {{ $kind === 'hotels' ? 'Tất cả khách sạn' : 'Tất cả địa danh' }}</a>
    @if($kind === 'hotels')
        @php($gallery = $record->images)
        @php($mainImage = $gallery->first())
        @php($fromPrice = $record->roomTypes->pluck('min_nightly_price')->filter()->min())
        <div class="mt-5 grid items-start gap-8 lg:grid-cols-[minmax(0,1.55fr)_minmax(310px,.75fr)]">
            <div class="min-w-0">
                <div class="hotel-gallery" data-hotel-gallery>
                    <div class="hotel-gallery-main" data-gallery-main-wrap>
                        @if($mainImage)<img data-gallery-main src="{{ $mainImage->url }}" alt="{{ $mainImage->caption }}" fetchpriority="high"><span class="hotel-gallery-caption" data-gallery-caption>{{ $mainImage->caption }}</span>
                        @else<div class="absolute inset-0 grid place-items-center text-sm text-muted">Ảnh khách sạn đang được bổ sung</div>@endif
                        <span class="hotel-gallery-count"><x-icon name="map" class="size-3.5"/> {{ $record->destination->name }} <span class="mx-1 text-white/50">·</span> {{ $gallery->count() }} ảnh</span>
                    </div>
                    @if($gallery->count() > 1)<div class="mt-3 grid grid-cols-2 gap-3">@foreach($gallery->skip(1)->take(2) as $image)
                        <button type="button" class="hotel-gallery-thumb group" data-gallery-pick data-src="{{ $image->url }}" data-alt="{{ $image->caption }}" data-caption="{{ $image->caption }}" aria-label="Xem ảnh: {{ $image->caption }}"><img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy"><span class="absolute inset-0 grid place-items-center bg-black/0 text-white opacity-0 transition group-hover:bg-black/25 group-hover:opacity-100"><x-icon name="search" class="size-6"/></span></button>
                    @endforeach</div>@endif
                </div>
                <div class="mt-8">
                    <p class="eyebrow flex items-center gap-2"><x-icon name="pin" class="size-4"/> VietGo stay <span class="text-[#ccb6a8]">/</span> {{ $record->destination->name }}</p>
                    <h1 class="mt-3 max-w-3xl font-serif text-4xl leading-tight sm:text-5xl">{{ $record->name }}</h1>
                    <p class="mt-5 max-w-3xl text-sm leading-8 text-muted">{{ $record->description }}</p>
                    <div class="mt-6 flex flex-wrap gap-2"><span class="hotel-info-chip"><x-icon name="map" class="size-4 text-brand-600"/> {{ $record->destination->name }}</span>@if($record->address)<span class="hotel-info-chip"><x-icon name="pin" class="size-4 text-brand-600"/> {{ $record->address }}</span>@endif</div>
                </div>
            </div>

            <aside class="hotel-booking-card lg:sticky lg:top-28">
                <div class="flex items-start justify-between gap-3"><div><p class="eyebrow">Lưu trú tại {{ $record->destination->name }}</p><h2 class="mt-2 font-serif text-2xl">Sẵn sàng lên kế hoạch?</h2></div><span class="grid size-11 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon name="calendar" class="size-5"/></span></div>
                @if($fromPrice)<p class="mt-7 text-xs text-muted">Giá tham khảo từ</p><p class="mt-1 font-serif text-4xl text-brand-700">{{ number_format((float) $fromPrice, 0, ',', '.') }}<span class="ml-1 font-sans text-sm font-medium">₫</span></p><p class="mt-1 text-[11px] text-muted">mỗi phòng · mỗi đêm</p>@endif
                <div class="mt-6 grid gap-3 border-y border-[#eee3da] py-5 text-xs"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-full bg-linen"><x-icon name="users" class="size-4 text-sage"/></span><span>Phòng có sức chứa tối đa <strong class="text-ink">{{ $record->roomTypes->max('max_guests') }} khách</strong></span></div><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-full bg-linen"><x-icon name="check" class="size-4 text-sage"/></span><span>Kiểm tra giá và tình trạng theo ngày</span></div></div>
                @if($record->roomTypes->isNotEmpty())<a href="{{ route('hotel-rooms.search', $record) }}" class="btn-primary mt-5 w-full">Chọn ngày & xem phòng <x-icon name="arrow" class="size-4"/></a>@else<p class="mt-5 rounded-xl bg-linen p-4 text-xs leading-6 text-muted">Khách sạn chưa có phòng mở bán qua VietGo.</p>@endif
                <p class="mt-4 text-[10px] leading-5 text-muted">Giá và tồn là mức tham khảo VietGo thiết lập, chưa kết nối với hệ thống bán phòng của khách sạn. Thanh toán trên phiên bản này được mô phỏng.</p>
                <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 border-t border-[#eee3da] pt-4"><a class="text-link" href="{{ $record->website_url }}" target="_blank" rel="noopener noreferrer">Website khách sạn <x-icon name="arrow-up" class="size-3.5"/></a>@if($record->address)<a class="text-link" href="{{ $record->google_maps_url ?: 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($record->name.' '.$record->address) }}" target="_blank" rel="noopener noreferrer">Mở bản đồ <x-icon name="arrow-up" class="size-3.5"/></a>@endif</div>
            </aside>
        </div>
    @else
        @php($hasDetails = filled($record->address))
        <div class="mt-8 {{ $hasDetails ? 'grid gap-8 lg:grid-cols-[1.5fr_1fr]' : 'max-w-4xl' }}">
            <div><h1 class="font-serif text-4xl leading-tight sm:text-5xl">{{ $record->name }}</h1><div class="mt-4 flex flex-wrap gap-3 text-sm text-brand-600">@foreach($record->destinations as $destination)<a class="underline" href="{{ route('destinations.show', $destination) }}">{{ $destination->name }}</a>@endforeach</div><p class="mt-6 text-sm leading-8 text-muted">{{ $record->description }}</p><x-image-gallery :images="$record->images" :name="$record->name"/></div>
            @if($hasDetails)<aside class="h-fit rounded-2xl bg-linen p-6"><h2 class="font-serif text-2xl">Thông tin địa điểm</h2><p class="mt-5 text-sm leading-7"><strong>Địa chỉ:</strong> {{ $record->address }}</p><a class="text-link mt-3" href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($record->name.' '.$record->address) }}" target="_blank" rel="noopener noreferrer">Tìm trên bản đồ ↗</a></aside>@endif
        </div>
    @endif

    @php($related = $kind === 'hotels' ? $places : $hotels)
    @if($related->isNotEmpty())<section class="mt-16 border-t border-[#e7dcd1] pt-12"><div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Khám phá thêm</p><h2 class="mt-2 font-serif text-3xl">{{ $kind === 'hotels' ? 'Điểm dừng chân quanh đây' : 'Khách sạn trong khu vực' }}</h2></div>@if($kind === 'hotels')<a class="text-link" href="{{ route('destinations.show', $record->destination).'#tours' }}">Tour tại {{ $record->destination->name }} <x-icon name="arrow" class="size-4"/></a>@endif</div><div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($related as $relatedRecord)@include('directory.card', ['record' => $relatedRecord, 'kind' => $kind === 'hotels' ? 'places' : 'hotels'])@endforeach</div></section>@endif
    @if($kind === 'hotels' && $tours->isNotEmpty())<section class="mt-14 rounded-3xl bg-linen p-6 sm:p-9"><div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Cùng điểm đến</p><h2 class="mt-2 font-serif text-3xl">Hành trình tại {{ $record->destination->name }}</h2></div><a class="text-link" href="{{ route('destinations.show', $record->destination).'#tours' }}">Xem tất cả <x-icon name="arrow" class="size-4"/></a></div><div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($tours as $tour)<x-tour-card :tour="$tour"/>@endforeach</div></section>@endif
</section>
@endsection
