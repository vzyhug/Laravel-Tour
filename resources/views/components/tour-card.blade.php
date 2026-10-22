@props(['tour'])
@php
    $liked = session('customer_id') && \Illuminate\Support\Facades\DB::table('favorites')
        ->where('customer_id', session('customer_id'))->where('tour_id', $tour->getKey())->exists();
    $reviewCount = $tour->review_total;
@endphp
<article class="tour-card group">
    <a href="{{ route('tours.show', ['tour' => $tour, ...request()->only('departure_date', 'guests')]) }}" class="tour-card-image block" tabindex="-1" aria-hidden="true">
        @if($tour->thumbnail_url)<img src="{{ $tour->thumbnail_url }}" alt="" loading="lazy">@endif
        <span class="absolute left-4 top-4 rounded-full bg-sand/95 px-3 py-1.5 text-[10px] font-semibold">{{ $tour->duration_days }} ngày · {{ $tour->duration_nights }} đêm</span>
        @if($reviewCount > 0)<span class="absolute bottom-4 left-4 flex items-center gap-1.5 rounded-full bg-sand/95 px-2.5 py-1.5 text-[10px]"><x-icon name="star" class="size-3.5 fill-brand-500 text-brand-500"/><strong>{{ number_format($tour->average_rating, 1) }}</strong><span class="text-muted">({{ $reviewCount }} đánh giá)</span></span>@endif
    </a>
    @if(session('customer_id'))
        <form method="POST" action="{{ route('favorites.toggle', $tour) }}" class="absolute right-3 top-3 z-10">@csrf<button type="submit" @class(['icon-button size-10 border-0', 'text-brand-600' => $liked]) aria-label="{{ $liked ? 'Bỏ lưu tour' : 'Lưu tour yêu thích' }}" aria-pressed="{{ $liked ? 'true' : 'false' }}"><x-icon name="bookmark" class="size-4 {{ $liked ? 'fill-brand-100' : '' }}"/></button></form>
    @else
        <a href="{{ route('login') }}" class="icon-button absolute right-3 top-3 z-10 size-10 border-0" aria-label="Đăng nhập để lưu tour"><x-icon name="bookmark" class="size-4"/></a>
    @endif
    <div class="flex flex-1 flex-col p-5 sm:p-6">
        <p class="eyebrow text-[9px] text-sage">{{ $tour->province->province_name }} · {{ $tour->category->category_name }}</p>
        <h3 class="mt-2 font-serif text-[22px] leading-snug"><a href="{{ route('tours.show', ['tour' => $tour, ...request()->only('departure_date', 'guests')]) }}" class="transition hover:text-brand-600">{{ $tour->title }}</a></h3>
        <p class="mt-3 line-clamp-2 text-xs leading-6 text-muted">{{ $tour->description }}</p>
        <div class="mt-5 flex flex-1 items-end"><div class="flex w-full items-end justify-between gap-3 border-t border-[#eee5dd] pt-5"><div><span class="block text-[9px] uppercase tracking-widest text-muted">Giá từ / người lớn</span><strong class="price-text mt-1 block font-serif text-xl font-bold">{{ number_format($tour->min_price, 0, ',', '.') }}₫</strong></div><a href="{{ route('tours.show', ['tour' => $tour, ...request()->only('departure_date', 'guests')]) }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-linen px-3 text-[10px] font-semibold transition hover:bg-brand-600 hover:text-white">Xem tour <x-icon name="arrow-up" class="size-3.5"/></a></div></div>
    </div>
</article>
