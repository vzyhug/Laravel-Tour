@extends('layouts.app')
@section('title', ($kind === 'hotels' ? 'Khách sạn' : 'Địa danh tham quan').' · VietGo')
@section('content')
@if($kind === 'hotels')
    @php($heroImage = $records->first()?->images->first())
    <section class="container-page pt-7 sm:pt-10">
        <a href="{{ route('destinations.index') }}" class="text-link">← Khám phá điểm đến</a>
        <div class="hotel-catalog-hero relative isolate mt-5 overflow-hidden rounded-[2rem] bg-[#35261f] text-white">
            @if($heroImage)<img src="{{ $heroImage->url }}" alt="{{ $heroImage->caption }}" class="absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">@endif
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#211a17]/90 via-[#211a17]/65 to-[#211a17]/10"></div>
            <div class="px-6 py-12 sm:px-12 sm:py-16 lg:px-16 lg:py-20">
                <p class="eyebrow text-brand-100">VietGo · Lưu trú · Nghỉ ngơi theo cách của bạn</p>
                <h1 class="mt-4 max-w-2xl font-serif text-4xl leading-tight sm:text-5xl lg:text-6xl">Một chốn dừng chân,<br><em class="font-normal text-[#ffd9c4]">một phần của chuyến đi.</em></h1>
                <p class="mt-5 max-w-xl text-sm leading-7 text-white/80">Tìm nơi lưu trú theo điểm đến. Xem ảnh, thông tin và mức giá phòng tham khảo trước khi chọn ngày.</p>
                <div class="mt-7 flex flex-wrap gap-3 text-xs">
                    <span class="rounded-full border border-white/25 bg-white/10 px-4 py-2.5 backdrop-blur">{{ $records->total() }} khách sạn trong cẩm nang</span>
                    <span class="rounded-full border border-white/25 bg-white/10 px-4 py-2.5 backdrop-blur">31 điểm đến Việt Nam</span>
                    <span class="rounded-full border border-white/25 bg-white/10 px-4 py-2.5 backdrop-blur">Giá hiển thị theo đêm lưu trú</span>
                </div>
            </div>
            <span class="pointer-events-none absolute bottom-7 right-8 hidden font-serif text-7xl italic text-white/20 lg:block">Đi & ở</span>
        </div>


    </section>

    <section class="container-page pt-14 sm:pt-16">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Bắt đầu từ điểm đến</p><h2 class="mt-2 font-serif text-3xl sm:text-4xl">Bạn muốn đến đâu?</h2></div><p class="max-w-sm text-xs leading-6 text-muted">Chọn một vùng để xem các khách sạn và hành trình gần nhau.</p></div>
        <nav class="mt-6 flex gap-3 overflow-x-auto pb-2" aria-label="Điểm đến khách sạn">
            <a href="{{ route('hotels.index', request()->except('destination', 'page')) }}" @class(['destination-pill', 'destination-pill-active' => !request('destination')])><span class="text-lg">✦</span><span>Tất cả</span><span class="destination-pill-count">{{ $destinations->sum('hotels_count') }}</span></a>
            @foreach($featuredDestinations as $destination)
                <a href="{{ route('hotels.index', array_merge(request()->except('page'), ['destination' => $destination->slug])) }}" @class(['destination-pill', 'destination-pill-active' => request('destination') === $destination->slug]) aria-current="{{ request('destination') === $destination->slug ? 'page' : 'false' }}"><span>{{ $destination->name }}</span><span class="destination-pill-count">{{ $destination->hotels_count }}</span></a>
            @endforeach
        </nav>
    </section>

    <section class="container-page py-12 sm:py-16">
        <form method="GET" class="sticky-search hotel-search-panel mb-7  grid gap-3 rounded-2xl border border-[#eadfd6] bg-white p-4 shadow-[0_18px_55px_rgba(58,40,25,.12)] sm:grid-cols-[1.1fr_1fr_auto] sm:p-5" role="search">
            <label class="hotel-search-field"><span class="eyebrow text-[9px] text-muted">Bạn muốn nghỉ ở đâu?</span><span class="mt-1 flex items-center gap-3"><x-icon name="search" class="size-5 text-brand-600"/><input name="q" maxlength="100" value="{{ request('q') }}" placeholder="Tên khách sạn hoặc địa chỉ" class="min-h-9 w-full bg-transparent text-sm outline-none placeholder:text-muted/70"></span></label>
            <label class="hotel-search-field"><span class="eyebrow text-[9px] text-muted">Chọn điểm đến</span><span class="mt-1 flex items-center gap-3"><x-icon name="pin" class="size-5 text-brand-600"/><select name="destination" class="min-h-9 w-full appearance-none bg-transparent text-sm outline-none"><option value="">Tất cả điểm đến</option>@foreach($destinations as $destination)<option value="{{ $destination->slug }}" @selected(request('destination') === $destination->slug)>{{ $destination->name }}</option>@endforeach</select><x-icon name="chevron" class="size-4 text-muted"/></span></label>
            <div class="flex items-center gap-2 sm:justify-end"><button class="btn-primary min-h-14 w-full sm:w-auto"><x-icon name="search" class="size-4"/> Tìm khách sạn</button>@if(request()->hasAny(['q', 'destination']))<a href="{{ route('hotels.index') }}" class="icon-button" aria-label="Xóa bộ lọc"><x-icon name="close" class="size-4"/></a>@endif</div>
        </form>
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[#e7dcd1] pb-5"><div><p class="eyebrow">Nơi lưu trú được tuyển chọn</p><h2 class="mt-2 font-serif text-3xl sm:text-4xl">{{ request('destination') ? 'Ở lại tại '.$destinations->firstWhere('slug', request('destination'))?->name : 'Khám phá khách sạn' }}</h2></div><p class="rounded-full bg-linen px-4 py-2 text-xs text-muted"><strong class="text-ink">{{ $records->total() }}</strong> kết quả</p></div>
        <div class="mt-7 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($records as $record) @include('directory.card', ['kind' => 'hotels']) @empty
                <div class="col-span-full rounded-3xl border border-dashed border-[#d9cabe] bg-linen/50 px-6 py-14 text-center"><span class="mx-auto grid size-14 place-items-center rounded-full bg-white text-brand-600"><x-icon name="search" class="size-6"/></span><h3 class="mt-4 font-serif text-2xl">Chưa tìm thấy nơi phù hợp</h3><p class="mt-2 text-sm text-muted">Thử tên khác hoặc chọn một điểm đến khác nhé.</p><a href="{{ route('hotels.index') }}" class="btn-secondary mt-5">Xem tất cả khách sạn</a></div>
            @endforelse
        </div>
        <div class="mt-9">{{ $records->links() }}</div>
    </section>
@else
    <section class="container-page py-10 sm:py-14">
        <a href="{{ route('destinations.index') }}" class="text-link">← Khám phá điểm đến</a><p class="eyebrow mt-8">Cẩm nang Việt Nam</p>
        <h1 class="section-title mt-3">Khám phá những địa danh.</h1>
        <form method="GET" class="sticky-search mt-8 grid gap-4 rounded-2xl bg-linen p-5 sm:grid-cols-2 lg:grid-cols-4" role="search">
            <label class="text-sm">Tên hoặc địa chỉ<input name="q" maxlength="100" value="{{ request('q') }}" placeholder="Bạn muốn tìm gì?" class="field mt-2"></label>
            <label class="text-sm">Điểm đến<select name="destination" class="field mt-2"><option value="">Tất cả điểm đến</option>@foreach($destinations as $destination)<option value="{{ $destination->slug }}" @selected(request('destination') === $destination->slug)>{{ $destination->name }}</option>@endforeach</select></label>
            <label class="text-sm">Loại địa danh<select name="category" class="field mt-2"><option value="">Tất cả loại</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="flex items-end gap-4"><button class="btn-primary">Tìm kiếm</button><a class="py-3 text-sm underline" href="{{ route('places.index') }}">Xóa lọc</a></div>
        </form>
        <p class="mt-6 text-sm text-muted">{{ $records->total() }} địa danh</p><div class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($records as $record) @include('directory.card', ['kind' => 'places']) @empty <p class="col-span-full py-10 text-muted">Chưa tìm thấy kết quả phù hợp. Hãy thử bỏ bớt bộ lọc.</p> @endforelse
        </div><div class="mt-8">{{ $records->links() }}</div>
    </section>
@endif
@endsection
