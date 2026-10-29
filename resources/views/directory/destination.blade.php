@extends('layouts.app')
@section('title', $destination->name.' · VietGo')
@section('content')
<section class="container-page py-10 sm:py-14">
    <a href="{{ route('destinations.index') }}" class="text-link">← Tất cả điểm đến</a>
    @php($cover = $destination->places->flatMap->images->first() ?? $destination->hotels->flatMap->images->first())
    <div class="relative mt-6 overflow-hidden rounded-3xl bg-ink">
        @if($cover)<img src="{{ $cover->url }}" alt="{{ $destination->name }}" class="absolute inset-0 size-full object-cover">@endif
        <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/60 to-black/20"></div>
        <div class="relative px-6 py-16 text-white sm:px-12 sm:py-24">
            <p class="text-xs font-semibold uppercase tracking-widest text-white/80">Miền {{ $destination->region }} · Đi theo cách của bạn</p>
            <h1 class="mt-4 font-serif text-5xl sm:text-7xl">{{ $destination->name }}</h1>
            <p class="mt-5 max-w-2xl text-sm leading-7 text-white/85">{{ $destination->description }}</p>
            <a href="#hotels" class="btn-primary mt-7">Chọn nơi nghỉ cho chuyến đi →</a>
        </div>
    </div>
    <div class="sticky top-20 z-20 mt-6 flex flex-wrap gap-3 rounded-xl border border-[#e7dcd1] bg-sand/95 p-3 backdrop-blur"><a class="btn-primary" href="#hotels">Tìm nơi lưu trú · {{ $destination->hotels->count() }} khách sạn</a><a class="btn-secondary" href="#places">Đi đâu, khám phá gì?</a><a class="btn-secondary" href="#tours">Tour trọn gói · {{ $tours->count() }}</a></div>
    <section id="hotels" class="scroll-mt-44 rounded-3xl bg-linen p-6 sm:p-10"><p class="eyebrow">Một chỗ nghỉ, cả hành trình của bạn</p><h2 class="mt-3 font-serif text-3xl sm:text-4xl">Ở đâu tại {{ $destination->name }}?</h2><p class="mt-4 max-w-2xl text-sm leading-7 text-muted">Tự lên lịch khám phá cùng bạn bè, gia đình. Chọn nơi nghỉ phù hợp và xem giá theo ngày lưu trú của bạn.</p><a class="text-link mt-4" href="{{ route('hotels.index', ['destination' => $destination->slug]) }}">Tìm khách sạn theo tên →</a><div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($destination->hotels as $record) @include('directory.card', ['kind' => 'hotels']) @empty <p class="text-sm text-muted">Danh mục khách sạn tại đây đang được bổ sung.</p> @endforelse
    </div></section>
    <section id="tours" class="scroll-mt-24 pt-12"><h2 class="font-serif text-3xl">Tour khám phá {{ $destination->name }}</h2>
        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@forelse($tours as $tour)<x-tour-card :tour="$tour"/>@empty<p class="text-sm text-muted">Chương trình tour tại đây đang được bổ sung.</p>@endforelse</div>
    </section>
    <section id="places" class="scroll-mt-24 pt-12"><h2 class="font-serif text-3xl">Địa danh tham quan</h2><div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($destination->places as $record) @include('directory.card', ['kind' => 'places']) @empty <p class="text-muted">Đang bổ sung địa danh cho điểm đến này.</p> @endforelse
    </div></section>

</section>
@endsection
