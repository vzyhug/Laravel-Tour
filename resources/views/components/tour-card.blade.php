@props(['tour'])
<article class="card group overflow-hidden transition hover:-translate-y-1 hover:shadow-lg">
    <a href="{{ route('tours.show', $tour) }}" class="block">
        <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-brand-100 via-cyan-100 to-amber-100">
            @if($tour->thumbnail_url)<img src="{{ $tour->thumbnail_url }}" alt="{{ $tour->title }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">@endif
            <span class="badge absolute left-4 top-4 bg-white/90">{{ $tour->category->category_name }}</span>
        </div>
        <div class="grid gap-3 p-5">
            <p class="text-sm font-semibold text-brand-700">{{ $tour->province->province_name }} · {{ $tour->duration_days }}N{{ $tour->duration_nights }}Đ</p>
            <h3 class="line-clamp-2 text-lg font-bold text-slate-900 group-hover:text-brand-700">{{ $tour->title }}</h3>
            <div class="flex items-end justify-between gap-3 border-t border-slate-100 pt-3"><span class="text-sm text-slate-500">Từ</span><strong class="text-xl text-orange-600">{{ number_format($tour->min_price, 0, ',', '.') }}₫</strong></div>
        </div>
    </a>
</article>
