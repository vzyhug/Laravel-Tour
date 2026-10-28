@extends('layouts.app')
@section('title', $tour->title.' · VietGo')
@section('content')
<div class="container-page pb-6 pt-7">
    <nav class="flex flex-wrap items-center gap-2 text-[10px] text-muted" aria-label="Đường dẫn"><a href="{{ route('home') }}" class="hover:text-brand-600">Trang chủ</a><span>/</span><a href="{{ route('tours.index') }}" class="hover:text-brand-600">Hành trình</a><span>/</span><a href="{{ route('provinces.tours', $tour->province) }}" class="text-brand-600">{{ $tour->province->province_name }}</a></nav>
    <div class="mt-8 flex items-start justify-between gap-4"><div class="max-w-4xl"><p class="eyebrow">{{ $tour->category->category_name }} · VietGo tuyển chọn</p><h1 class="mt-3 font-serif text-3xl leading-tight tracking-tight sm:text-5xl">{{ $tour->title }}</h1><p class="mt-4 max-w-3xl text-sm leading-7 text-muted">{{ $tour->itinerary_summary }}</p></div><div class="shrink-0">@if(session('customer_id'))<form method="POST" action="{{ route('favorites.toggle', $tour) }}">@csrf<button class="icon-button" aria-label="Lưu hoặc bỏ lưu tour yêu thích"><x-icon name="bookmark"/></button></form>@else<a href="{{ route('login') }}" class="icon-button" aria-label="Đăng nhập để lưu tour"><x-icon name="bookmark"/></a>@endif</div></div>
    <div class="mt-7 grid gap-4 overflow-hidden rounded-xl {{ $tour->images->count() > 1 ? 'sm:grid-cols-[1.65fr_1fr]' : '' }}">
        <div class="relative aspect-[16/10] overflow-hidden bg-linen">@if($tour->thumbnail_url)<img src="{{ $tour->thumbnail_url }}" class="size-full object-cover" alt="{{ $tour->title }}" fetchpriority="high">@endif<span class="absolute bottom-5 left-5 flex items-center gap-2 rounded-full bg-sand/95 px-3 py-2 text-[10px]"><x-icon name="pin" class="size-3.5 text-brand-600"/>{{ $tour->province->province_name }}, Việt Nam</span></div>
        @if($tour->images->count() > 1)<div class="hidden h-full min-h-0 overflow-hidden sm:block"><img src="{{ $tour->images->firstWhere('img_url', '!=', $tour->thumbnail_url)?->img_url ?? $tour->thumbnail_url }}" class="size-full object-cover" alt="Một góc nhìn khác của {{ $tour->title }}"></div>@endif
    </div>
    <div class="grid gap-5 border-b border-[#e5d9cf] py-6 sm:grid-cols-3"><div class="flex items-center gap-3"><x-icon name="calendar" class="size-5 text-brand-600"/><div><p class="eyebrow text-[9px] text-muted">Thời gian</p><p class="mt-1 text-xs font-semibold">{{ $tour->duration_days }} ngày · {{ $tour->duration_nights }} đêm</p></div></div><div class="flex items-center gap-3"><x-icon name="pin" class="size-5 text-brand-600"/><div><p class="eyebrow text-[9px] text-muted">Điểm đón</p><p class="mt-1 text-xs font-semibold">{{ $tour->pickup_point ?: 'Theo lịch khởi hành' }}</p></div></div><a href="#reviews" class="flex items-center gap-3"><x-icon name="star" class="size-5 text-brand-600"/><div><p class="eyebrow text-[9px] text-muted">Trải nghiệm thực tế</p><p class="mt-1 text-xs font-semibold">{{ number_format($tour->reviews->avg('rating') ?: 0, 1) }} / 5 <span class="font-normal text-muted">({{ $tour->reviews->count() }} đánh giá)</span></p></div></a></div>
</div>
<section class="container-page grid items-start gap-10 pb-12 lg:grid-cols-[minmax(0,1fr)_350px] xl:gap-16">
    <article class="min-w-0">
        <nav class="mb-8 flex gap-5 overflow-x-auto border-b border-[#e5d9cf] text-xs font-semibold text-muted" aria-label="Nội dung tour"><a href="#overview" class="whitespace-nowrap border-b-2 border-brand-600 pb-4 text-brand-600">Câu chuyện hành trình</a><a href="#itinerary" class="whitespace-nowrap pb-4 hover:text-brand-600">Lịch trình</a><a href="#inclusions" class="whitespace-nowrap pb-4 hover:text-brand-600">Dịch vụ bao gồm</a><a href="#reviews" class="whitespace-nowrap pb-4 hover:text-brand-600">Đánh giá</a></nav>
        <section id="overview" class="detail-section"><p class="eyebrow">Cảm hứng từ những vùng đất</p><h2 class="mt-2 font-serif text-3xl">Một hành trình, nhiều điều để nhớ.</h2><p class="mt-5 whitespace-pre-line text-sm leading-8 text-muted">{{ $tour->description }}</p></section>
        <section id="itinerary" class="detail-section">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="eyebrow">Từng ngày, từng trải nghiệm</p><h2 class="mt-2 font-serif text-3xl">Lịch trình của bạn.</h2></div>@if($tour->schedules->isNotEmpty())<button type="button" data-expand-itinerary class="text-link text-[10px]">Mở tất cả <x-icon name="plus" class="size-4"/></button>@endif</div>
            <p class="mt-4 text-xs text-muted">Kéo tay nắm ⠿ để đổi thứ tự; số ngày sẽ tự cập nhật theo vị trí mới.</p>
            <div class="mt-6 grid gap-3" data-itinerary>
                @forelse($tour->schedules as $schedule)
                    <details class="itinerary-day" data-itinerary-day @if($loop->first) open @endif>
                        <summary>
                            <span class="day-drag-handle" data-day-drag-handle role="button" tabindex="0" aria-label="K&#xE9;o &#x111;&#x1EC3; s&#x1EAF;p x&#x1EBF;p Ng&#xE0;y {{ $schedule->day_number }}" title="K&#xE9;o &#x111;&#x1EC3; &#x111;&#x1ED5;i th&#x1EE9; t&#x1EF1; ng&#xE0;y">&#x28FF;</span>
                            <span class="font-serif text-3xl text-brand-600/65" data-day-ordinal>{{ str_pad($schedule->day_number, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="flex-1"><span class="eyebrow text-[9px] text-muted" data-day-label>Ng&#xE0;y {{ $schedule->day_number }}</span><span class="mt-1 block font-serif text-lg">&#x110;&#x1ECB;a &#x111;i&#x1EC3;m trong ng&#xE0;y</span></span>
                            <x-icon name="chevron" class="itinerary-chevron size-4 shrink-0 text-muted transition"/>
                        </summary>
                        <div class="border-t border-[#eee5dd] px-5 py-5 sm:pl-16">
                            @include('itineraries.day-stops')
                        </div>
                    </details>
                @empty
                    <p class="text-sm leading-8 text-muted">{{ $tour->itinerary_summary ?: 'L&#x1ECB;ch tr&#xEC;nh chi ti&#x1EBF;t s&#x1EBD; &#x111;&#x1B0;&#x1EE3;c c&#x1EAD;p nh&#x1EAD;t.' }}</p>
                @endforelse
            </div>
        </section>
        @include('itineraries.editor')
        <section id="inclusions" class="detail-section"><p class="eyebrow">Chăm chút từng chi tiết</p><h2 class="mt-2 font-serif text-3xl">Dịch vụ bao gồm.</h2><div class="mt-5 flex items-start gap-3 rounded-xl bg-linen/70 p-6"><x-icon name="check" class="mt-1 size-5 shrink-0 text-sage"/><p class="whitespace-pre-line text-sm leading-8 text-muted">{{ $tour->included_services ?: 'Liên hệ VietGo để được tư vấn chi tiết dịch vụ.' }}</p></div></section>
        <section id="reviews" class="detail-section"><p class="eyebrow">Ký ức từ những người đồng hành</p><h2 class="mt-2 font-serif text-3xl">Lời kể sau hành trình.</h2><div class="mt-6 grid gap-4">@forelse($tour->reviews as $review)<article class="card p-5 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-full bg-linen font-serif text-xl text-brand-600">{{ mb_substr($review->customer->full_name, 0, 1) }}</span><strong class="text-xs">{{ $review->customer->full_name }}</strong></div><span class="text-sm tracking-widest text-brand-500" aria-label="{{ $review->rating }} trên 5 sao">{{ str_repeat('★', $review->rating) }}</span></div><p class="mt-4 text-sm leading-7 text-muted">{{ $review->feedback }}</p></article>@empty<p class="rounded-xl bg-linen/70 p-6 text-sm text-muted">Chưa có đánh giá. Những câu chuyện đầu tiên đang chờ bạn viết nên.</p>@endforelse</div></section>
    </article>
    <aside class="lg:sticky lg:top-28">
        <div class="card overflow-hidden">
            <div class="border-b border-[#e9dfd6] bg-[#f9f2ed] p-6"><p class="eyebrow">Hành trình trọn vẹn</p><p class="mt-3 font-serif text-4xl text-brand-600">{{ number_format($tour->min_price, 0, ',', '.') }}₫ <span class="font-sans text-[10px] text-muted">/ người lớn, từ</span></p><p class="mt-3 text-xs leading-6 text-muted">{{ $tour->duration_days }} ngày khám phá · {{ $tour->category->category_name }}</p></div>
            <div class="p-6"><h2 class="font-serif text-xl">Chọn ngày lên đường.</h2><p class="mt-2 text-[11px] leading-6 text-muted">Giá và số chỗ được cập nhật theo từng lịch khởi hành.</p><div class="mt-5 grid max-h-96 gap-3 overflow-y-auto">@forelse($tour->departures as $departure)<a href="{{ route('bookings.create', $departure) }}" class="group rounded-lg border border-[#e5d9cf] p-4 transition hover:border-brand-500 hover:bg-brand-50"><div class="flex items-center justify-between gap-2"><strong class="flex items-center gap-2 text-xs"><x-icon name="calendar" class="size-4 text-brand-600"/>{{ $departure->departure_date->format('d/m/Y') }} @if($departure->time_label) · {{ $departure->time_label }} @endif</strong><span class="text-[10px] text-sage">Còn {{ $departure->available_seats }} chỗ</span></div><div class="mt-3 flex items-center justify-between gap-2"><span class="font-serif text-lg">{{ number_format($departure->adult_price, 0, ',', '.') }}₫</span><span class="flex items-center gap-1 text-[10px] font-semibold text-brand-600">Đặt chuyến đi <x-icon name="arrow" class="size-3.5"/></span></div></a>@empty<p class="rounded-lg bg-linen p-4 text-xs leading-6 text-muted">Chưa có lịch khởi hành sắp tới. Liên hệ để được tư vấn.</p>@endforelse</div></div>
            <div class="flex items-start gap-3 border-t border-[#e9dfd6] bg-sand p-5"><x-icon name="shield" class="size-5 shrink-0 text-sage"/><p class="text-[10px] leading-6 text-muted">Xem lại thông tin hành khách và tổng chi phí trước khi hoàn tất thanh toán.</p></div>
        </div>
        <a href="tel:19006868" class="btn-secondary mt-4 w-full"><x-icon name="phone" class="size-4"/> Tư vấn hành trình · 1900 6868</a>
    </aside>
</section>
@endsection
