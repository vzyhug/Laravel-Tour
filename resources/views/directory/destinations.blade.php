@extends('layouts.app')
@section('title', 'Điểm đến & địa danh · VietGo')
@section('content')
<section class="container-page py-10 sm:py-14">
    <p class="eyebrow">Cẩm nang khám phá</p>
    <h1 class="section-title mt-3">Một điểm đến, nhiều trải nghiệm.</h1>
    <p class="mt-4 max-w-2xl text-sm leading-7 text-muted">Tìm địa danh tham quan và nơi lưu trú theo từng vùng du lịch. Các điểm đến được nhóm theo hành trình để bạn dễ khám phá.</p>
    <div class="mt-5 flex flex-wrap gap-4"><a class="text-link" href="{{ route('places.index') }}">Tất cả địa danh <x-icon name="arrow" class="size-4"/></a><a class="text-link" href="{{ route('hotels.index') }}">Khách sạn <x-icon name="arrow" class="size-4"/></a></div>
    <form method="GET" class="mt-8 flex max-w-xl gap-3" role="search">
        <label class="flex-1"><span class="sr-only">Tên điểm đến</span><input name="q" maxlength="100" value="{{ request('q') }}" placeholder="Tìm Vũng Tàu, Đà Lạt, Hà Nội…" class="w-full rounded-xl border border-[#e7dcd1] bg-white p-3"></label><button class="btn-primary">Tìm kiếm</button>
    </form>
    <p class="mt-6 text-sm text-muted">{{ $destinations->total() }} điểm đến</p>
    <div class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($destinations as $destination)
            @php($image = $destination->places->flatMap->images->first())
            <a href="{{ route('destinations.show', $destination) }}" class="overflow-hidden rounded-2xl border border-[#e7dcd1] bg-white">
                @if($image)<img src="{{ $image->url }}" alt="{{ $destination->name }} — {{ $image->caption }}" loading="lazy" class="aspect-video w-full object-cover">@else<div class="aspect-video bg-linen"></div>@endif
                <div class="p-5"><p class="eyebrow text-muted">Miền {{ $destination->region }}</p><h2 class="mt-2 font-serif text-3xl">{{ $destination->name }}</h2><p class="mt-3 text-sm text-muted">{{ $destination->places_count }} địa danh · {{ $destination->hotels_count }} khách sạn</p></div>
            </a>
        @empty
            <p class="col-span-full py-10 text-muted">Chưa tìm thấy điểm đến phù hợp. Hãy thử tên khác.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $destinations->links() }}</div>
</section>
@endsection
