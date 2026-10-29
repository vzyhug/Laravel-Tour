@extends('layouts.app')
@section('title', 'Điểm đến & nơi lưu trú · VietGo')
@section('content')
@php
    $featured = $destinations->getCollection()->filter(fn ($item) => $item->places->flatMap->images->isNotEmpty() || $item->hotels->flatMap->images->isNotEmpty())->take(3)->values();
@endphp
<section class="container-page pb-12 pt-8 sm:pb-16 sm:pt-12">
    <div class="grid items-center gap-10 lg:grid-cols-[1fr_1.05fr] lg:gap-12">
        <div class="py-3 lg:py-10">
            <span class="inline-flex items-center gap-2 rounded-full border border-[#dfd8cc] px-3 py-2 text-[10px] font-semibold uppercase tracking-widest text-brand-700"><x-icon name="pin" class="size-3.5"/> Việt Nam · Những miền đáng đến</span>
            <h1 class="mt-6 font-serif text-5xl leading-[1.15] tracking-tight text-ink sm:text-6xl xl:text-7xl">Đi đâu cũng được.<br><em class="text-brand-600">Miễn là cùng nhau.</em></h1>
            <p class="mt-6 max-w-md text-sm leading-7 text-muted">Một cuối tuần bên biển, vài ngày giữa núi rừng hay hẹn nhau ở một thành phố mới. Chọn điểm đến, tìm nơi nghỉ và bắt đầu chuyến đi theo cách của bạn.</p>
            <div class="mt-7 flex flex-wrap items-center gap-5"><a href="#destination-list" class="btn-primary">Chọn miền muốn đến <x-icon name="arrow" class="size-4"/></a><a href="{{ route('hotels.index') }}" class="text-link">Tìm khách sạn</a></div>
            <div class="mt-9 flex items-center gap-3 border-t border-[#e7dcd1] pt-5 text-[11px] text-muted"><x-icon name="map" class="size-5 text-brand-600"/><span>Tự chọn nơi ở. Tự lên lịch đi. <strong class="font-semibold text-ink">Trọn vẹn trải nghiệm.</strong></span></div>
        </div>
        @if($featured->isNotEmpty())
            <div class="grid min-h-[380px] grid-cols-2 gap-3 sm:min-h-[460px] sm:gap-4">
                @foreach($featured as $item)
                    @php($photo = $item->places->flatMap->images->first() ?? $item->hotels->flatMap->images->first())
                    <a href="{{ route('destinations.show', $item) }}" @class(['group relative isolate overflow-hidden rounded-3xl bg-ink', 'row-span-2 min-h-96' => $loop->first, 'min-h-44' => !$loop->first, 'col-span-2' => $featured->count() === 1])>
                        <img src="{{ $photo->url }}" alt="{{ $item->name }}" @if(!$loop->first) loading="lazy" @else fetchpriority="high" @endif class="absolute inset-0 -z-20 size-full object-cover transition duration-700 motion-safe:group-hover:scale-105">
                        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/85 via-black/10 to-black/5"></div>
                        <span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1.5 text-[9px] font-bold uppercase tracking-widest text-ink">Miền {{ $item->region }}</span>
                        <div class="absolute inset-x-0 bottom-0 p-5 text-white sm:p-6"><p @class(['font-serif', 'text-3xl sm:text-4xl' => $loop->first, 'text-2xl' => !$loop->first])>{{ $item->name }}</p><p class="mt-2 text-[10px] text-white/85">{{ $item->hotels_count }} khách sạn · Khám phá →</p></div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <section id="destination-list" class="scroll-mt-28 pt-12 sm:pt-20">
        <div class="sticky-search flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-[#e7dcd1] bg-sand p-4 sm:p-5">
            <div><p class="eyebrow">Bản đồ những cuộc hẹn</p><h2 class="mt-3 font-serif text-3xl sm:text-4xl">Miền nào đang gọi bạn?</h2><p class="mt-3 text-xs leading-6 text-muted">{{ $destinations->total() }} điểm đến{{ filled(request('q')) ? ' phù hợp với tìm kiếm' : ' để chọn nơi dừng chân và khám phá' }}.</p></div>
            <form method="GET" action="{{ route('destinations.index') }}#destination-list" class="flex w-full items-center gap-2 rounded-2xl border border-[#e7dcd1] bg-white p-2 shadow-sm sm:max-w-md" role="search">
                <x-icon name="search" class="ml-2 size-5 shrink-0 text-brand-600"/>
                <label class="min-w-0 flex-1"><span class="sr-only">Tìm điểm đến</span><input type="search" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Bạn muốn đến đâu?" class="min-h-11 w-full rounded-lg border-0 bg-transparent px-2 text-xs outline-none focus:ring-2 focus:ring-brand-500"></label>
                <button class="btn-primary min-h-11 px-4 text-xs">Tìm kiếm</button>
            </form>
        </div>
        @if(filled(request('q')))<div class="mt-5 flex items-center gap-3 text-xs text-muted"><span>Kết quả cho “{{ request('q') }}”</span><a href="{{ route('destinations.index') }}#destination-list" class="text-link">Xóa tìm kiếm ×</a></div>@endif
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($destinations as $destination)
                @php($image = $destination->places->flatMap->images->first() ?? $destination->hotels->flatMap->images->first())
                <article class="group overflow-hidden rounded-2xl border border-[#e7dcd1] bg-white transition duration-300 hover:shadow-xl">
                    <a href="{{ route('destinations.show', $destination) }}" class="relative block aspect-[4/3] overflow-hidden bg-linen">
                        @if($image)<img src="{{ $image->url }}" alt="{{ $destination->name }}" loading="lazy" class="size-full object-cover transition duration-700 motion-safe:group-hover:scale-105">@else<div class="grid size-full place-items-center"><x-icon name="map" class="size-12 text-brand-600"/></div>@endif
                        <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-transparent to-black/10"></div>
                        <span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1.5 text-[9px] font-bold uppercase tracking-widest text-ink">Miền {{ $destination->region }}</span>
                        <h3 class="absolute bottom-5 left-5 right-5 font-serif text-3xl text-white">{{ $destination->name }}</h3>
                    </a>
                    <div class="p-5 sm:p-6">
                        <p class="line-clamp-2 min-h-12 text-xs leading-6 text-muted">{{ $destination->description ?: 'Khám phá địa danh, chọn nơi lưu trú và tự lên lịch cho chuyến đi của bạn.' }}</p>
                        <div class="mt-5 flex flex-wrap gap-2 text-[10px]"><span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-2 font-semibold text-brand-700"><x-icon name="bed" class="size-3.5"/>{{ $destination->hotels_count }} khách sạn</span><span class="rounded-full bg-sand px-3 py-2 text-muted">{{ $destination->places_count }} nơi khám phá</span></div>
                        <div class="mt-5 flex items-center justify-between gap-3 border-t border-[#eee5dd] pt-4"><a href="{{ route('destinations.show', $destination) }}" class="text-link text-[11px]">Khám phá điểm đến →</a><a href="{{ route('destinations.show', $destination) }}#hotels" class="inline-flex min-h-10 items-center rounded-lg bg-linen px-3 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-600 hover:text-white">Tìm nơi nghỉ</a></div>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-[#dacdbf] bg-white p-10 text-center"><x-icon name="search" class="mx-auto size-8 text-brand-600"/><h3 class="mt-4 font-serif text-2xl">Chưa tìm thấy miền bạn muốn đến</h3><p class="mt-3 text-sm text-muted">Thử tên ngắn hơn hoặc khám phá tất cả điểm đến.</p><a href="{{ route('destinations.index') }}#destination-list" class="btn-secondary mt-5">Xem tất cả điểm đến</a></div>
            @endforelse
        </div>
        <div class="mt-8">{{ $destinations->fragment('destination-list')->links() }}</div>
    </section>
    <div class="mt-12 flex flex-wrap items-center justify-between gap-6 rounded-3xl bg-[#eee6dc] p-6 sm:mt-16 sm:p-10"><div><p class="eyebrow">Đã biết mình muốn đi đâu?</p><h2 class="mt-3 font-serif text-2xl sm:text-3xl">Một nơi nghỉ hợp ý, một chuyến đi đáng nhớ.</h2><p class="mt-3 text-xs leading-6 text-muted">Tìm khách sạn theo điểm đến và chọn ngày lưu trú của bạn.</p></div><a href="{{ route('hotels.index') }}" class="btn-primary">Khám phá khách sạn <x-icon name="arrow" class="size-4"/></a></div>
</section>
@endsection
