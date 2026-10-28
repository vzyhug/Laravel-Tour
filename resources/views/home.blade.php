@extends('layouts.app')
@section('title', 'VietGo · Khám phá Việt Nam theo cách của bạn')
@section('content')
<section class="relative isolate overflow-hidden bg-linen">
    <img src="{{ app(\App\Services\Media\MediaCatalog::class)->url(config('media.hero')) }}" alt="Phong cảnh Việt Nam trong ánh nắng dịu" class="absolute inset-0 -z-20 size-full object-cover object-center" fetchpriority="high">
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-sand/95 via-sand/65 to-sand/10"></div>
    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-sand via-transparent to-sand/10"></div>
    <div class="container-page flex min-h-[700px] flex-col justify-between py-9 sm:min-h-[750px] sm:py-12">
        <div class="flex items-center justify-between gap-4"><span class="inline-flex items-center gap-2 rounded-full bg-sand/90 px-4 py-2 text-[9px] font-semibold uppercase tracking-[.16em]"><span class="size-1.5 rounded-full bg-brand-600"></span> Việt Nam · Những hành trình đáng nhớ</span><span class="hidden font-serif text-lg italic text-brand-600 sm:block">The art of slow travel.</span></div>
        <div class="max-w-3xl py-12 sm:py-16">
            <p class="eyebrow mb-5">Đi để cảm nhận. Ở lại để yêu.</p>
            <h1 class="font-serif text-[44px] font-normal leading-[1.12] tracking-tight sm:text-[64px] lg:text-[72px]">Mỗi hành trình,<br>một <em class="text-brand-600">miền cảm hứng.</em></h1>
            <p class="mt-6 max-w-lg text-sm leading-7 text-[#56433c] sm:text-base sm:leading-8">Từ những thung lũng mờ sương đến bờ biển ngập nắng. Khám phá Việt Nam qua những hành trình chọn lọc, theo nhịp điệu của riêng bạn.</p>
            <a class="text-link mt-5" href="#collections">Khám phá bộ sưu tập <x-icon name="arrow" class="size-4"/></a>
        </div>
        <form action="{{ route('home') }}" method="GET" class="grid gap-3 rounded-2xl bg-white/95 p-4 shadow-[0_15px_60px_rgba(58,40,25,.09)] sm:grid-cols-2 sm:p-5 lg:grid-cols-[1.4fr_1.1fr_1fr_auto]" aria-label="Tìm kiếm tour">
            <label class="rounded-lg bg-sand px-4 py-3"><span class="eyebrow flex items-center justify-between text-[9px] text-muted">Điểm đến mơ ước <x-icon name="search" class="size-4 text-brand-600"/></span><input name="q" value="{{ request('q') }}" placeholder="Bạn muốn đi đâu?" class="mt-2 min-h-8 w-full bg-transparent font-serif text-lg outline-none placeholder:text-ink"><span class="mt-1 block text-[10px] text-muted">Núi rừng, biển xanh hay phố cổ?</span></label>
            <label class="rounded-lg bg-sand px-4 py-3"><span class="eyebrow flex items-center justify-between text-[9px] text-muted">Vùng đất khám phá <x-icon name="pin" class="size-4 text-brand-600"/></span><select name="province" class="mt-2 min-h-8 w-full bg-transparent font-serif text-lg"><option value="">Mọi tỉnh thành</option>@foreach($provinces as $province)<option value="{{ $province->getKey() }}" @selected(request('province') == $province->getKey())>{{ $province->province_name }}</option>@endforeach</select><span class="mt-1 block text-[10px] text-muted">Những điểm đến trên khắp Việt Nam</span></label>
            <div class="hidden rounded-lg bg-sand px-4 py-3 lg:block"><p class="eyebrow flex items-center justify-between text-[9px] text-muted">Nhịp điệu của bạn <x-icon name="leaf" class="size-4 text-brand-600"/></p><p class="mt-2 min-h-8 font-serif text-lg">Chậm lại, khám phá sâu</p><p class="mt-1 text-[10px] text-muted">Trải nghiệm bản địa, dấu ấn riêng</p></div>
            <button class="btn-primary self-center sm:col-span-2 lg:col-span-1 lg:min-h-16">Tìm hành trình <x-icon name="arrow" class="size-4"/></button>
        </form>
    </div>
</section>

<section id="collections" class="container-page py-14 sm:py-20">
    <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end"><div><p class="eyebrow flex items-center gap-2"><x-icon name="sparkle" class="size-4"/> Những miền cảm hứng</p><h2 class="section-title mt-2">Việt Nam, qua từng góc nhìn.</h2></div><div class="flex flex-wrap gap-2"><a href="{{ route('tours.index') }}" class="rounded-full bg-ink px-4 py-2.5 text-[10px] font-semibold text-sand">Tất cả hành trình</a>@foreach(['Bắc', 'Trung', 'Nam'] as $region)<a href="{{ route('tours.index', ['region' => $region]) }}" class="rounded-full bg-linen px-4 py-2.5 text-[10px] font-semibold text-muted hover:bg-brand-100 hover:text-brand-700">Miền {{ $region }}</a>@endforeach</div></div>
    @if($tours->isNotEmpty())
        <div class="mt-8 grid gap-5 lg:grid-cols-12">
            @foreach($tours->take(3) as $tour)
                <a href="{{ route('tours.show', $tour) }}" @class(['editorial-image group flex flex-col justify-between p-6 text-white sm:p-8', 'min-h-[440px] lg:col-span-7 lg:row-span-2 lg:min-h-[560px]' => $loop->first, 'min-h-[265px] lg:col-span-5' => !$loop->first])>
                    @if($tour->thumbnail_url)<img src="{{ $tour->thumbnail_url }}" alt="{{ $tour->title }}" class="group-hover:scale-105" loading="lazy">@endif
                    <div class="flex items-center justify-between gap-3"><span class="rounded-full bg-sand/95 px-3 py-1.5 text-[9px] font-semibold uppercase tracking-widest text-brand-700">{{ $loop->first ? 'VietGo tuyển chọn' : $tour->category->category_name }}</span><x-icon name="arrow-up" class="size-5"/></div>
                    <div class="mt-16"><p class="text-[10px] uppercase tracking-[.18em] text-brand-100">{{ $tour->province->province_name }} · {{ $tour->duration_days }} ngày {{ $tour->duration_nights }} đêm</p><h3 class="mt-3 max-w-xl font-serif text-2xl leading-snug sm:text-3xl">{{ $tour->title }}</h3>@if($loop->first)<p class="mt-3 max-w-lg line-clamp-2 text-xs leading-6 text-white/80">{{ $tour->description }}</p>@endif<div class="mt-5 flex items-end justify-between gap-3 border-t border-white/25 pt-4"><span><span class="block text-[9px] uppercase tracking-widest text-white/70">Hành trình từ</span><strong class="mt-1 block font-serif text-xl font-normal">{{ number_format($tour->min_price, 0, ',', '.') }}₫ <span class="font-sans text-[10px] text-white/70">/ người</span></strong></span><span class="flex items-center gap-2 text-[10px] font-semibold text-brand-100">Xem lịch trình <x-icon name="arrow" class="size-4"/></span></div></div>
                </a>
            @endforeach
        </div>
    @else
        <div class="card mt-8 p-12 text-center"><h3 class="font-serif text-2xl">Chưa có tour phù hợp.</h3><p class="mt-3 text-sm text-muted">Thử một điểm đến hoặc tỉnh thành khác nhé.</p><a class="text-link mt-3" href="{{ route('home') }}">Xem tất cả hành trình <x-icon name="arrow" class="size-4"/></a></div>
    @endif
</section>

<section class="bg-[#f3ede7]/65 py-14 sm:py-20">
    <div class="container-page"><div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Được yêu thích · Đáng trải nghiệm</p><h2 class="section-title mt-2">Hành trình dành cho bạn.</h2></div><a href="{{ route('tours.index') }}" class="text-link">Xem tất cả tour <x-icon name="arrow" class="size-4"/></a></div><div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">@foreach($tours->take(6) as $tour)<x-tour-card :tour="$tour"/>@endforeach</div></div>
</section>

<section id="destinations" class="container-page py-14 sm:py-20">
    <div class="mx-auto max-w-xl text-center"><p class="eyebrow">Bản đồ của những ký ức</p><h2 class="section-title mt-2">Mỗi vùng đất, một câu chuyện.</h2><p class="mt-4 text-sm leading-7 text-muted">Để những điều nhỏ bé dẫn lối: một con đường ven biển, một buổi chợ sớm, hay nụ cười của người bản địa.</p></div>
    <div class="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">@foreach($tours->unique('province_id')->take(4) as $tour)<a href="{{ route('provinces.tours', $tour->province) }}" class="editorial-image group flex min-h-72 flex-col justify-end p-6 text-white">@if($tour->thumbnail_url)<img src="{{ $tour->thumbnail_url }}" alt="{{ $tour->province->province_name }}" class="group-hover:scale-105" loading="lazy">@endif<p class="text-[9px] uppercase tracking-[.18em] text-brand-100">Miền {{ $tour->province->region }}</p><div class="mt-2 flex items-center justify-between"><h3 class="font-serif text-2xl">{{ $tour->province->province_name }}</h3><x-icon name="arrow-up" class="size-4"/></div></a>@endforeach</div>
</section>

<section id="why-vietgo" class="bg-linen py-14 sm:py-20">
    <div class="container-page"><div class="mx-auto max-w-xl text-center"><p class="eyebrow">Triết lý hành trình</p><h2 class="section-title mt-2">Lời hứa từ VietGo.</h2><p class="mt-4 text-sm leading-7 text-muted">Chúng tôi chăm chút từng chi tiết để bạn có thể dành trọn tâm trí cho những trải nghiệm trên đường.</p></div><div class="mt-10 grid gap-5 md:grid-cols-3">@foreach([['map', '01 / Được tuyển chọn', 'Đi sâu vào bản sắc', 'Những lịch trình gắn với thiên nhiên, văn hóa và con người địa phương. Mỗi điểm dừng đều mang đến một góc nhìn mới.'], ['shield', '02 / Luôn minh bạch', 'An tâm từ lúc bắt đầu', 'Giá tour, dịch vụ bao gồm và lịch khởi hành được hiển thị rõ ràng. Bạn biết mình chọn gì trước khi đặt chuyến đi.'], ['leaf', '03 / Đồng hành tận tâm', 'Chậm lại để tận hưởng', 'Một chuyến đi không chỉ là những địa danh. Đó còn là khoảng thời gian để nghỉ ngơi, kết nối và tạo nên ký ức.']] as [$icon, $number, $title, $description])<article class="rounded-xl bg-white p-7 sm:p-9"><span class="grid size-12 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-6"/></span><p class="eyebrow mt-6 text-muted">{{ $number }}</p><h3 class="mt-2 font-serif text-2xl">{{ $title }}</h3><p class="mt-4 text-xs leading-7 text-muted">{{ $description }}</p></article>@endforeach</div></div>
</section>
@endsection
