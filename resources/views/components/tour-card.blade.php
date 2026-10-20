@props(['tour'])
<article class="card group relative overflow-hidden transition hover:-translate-y-1 hover:shadow-lg">
    @php
        $liked = session('customer_id') && \Illuminate\Support\Facades\DB::table('favorites')
            ->where('customer_id', session('customer_id'))
            ->where('tour_id', $tour->getKey())
            ->exists();
    @endphp
    @if (session('customer_id'))
        <form method="POST" action="{{ route('favorites.toggle', $tour) }}" style="position:absolute;top:1rem;right:1rem;z-index:10">
            @csrf
            <button type="submit" aria-label="Yêu thích" style="width:2.25rem;height:2.25rem;border-radius:9999px;background:rgba(255,255,255,.9);font-size:1.25rem;line-height:1;color:{{ $liked ? '#e11d48' : '#64748b' }}">{{ $liked ? '♥' : '♡' }}</button>
        </form>
    @else
        <a href="{{ route('login') }}" aria-label="Đăng nhập để lưu tour" style="position:absolute;top:1rem;right:1rem;z-index:10;display:grid;place-items:center;width:2.25rem;height:2.25rem;border-radius:9999px;background:rgba(255,255,255,.9);font-size:1.25rem;color:#64748b">♡</a>
    @endif
    <a href="{{ route('tours.show', $tour) }}" class="block">
        <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-brand-100 via-cyan-100 to-amber-100">
            @if ($tour->thumbnail_url)
                <img src="{{ $tour->thumbnail_url }}" alt="{{ $tour->title }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
            @endif
            <span class="badge absolute left-4 top-4 bg-white/90">{{ $tour->category->category_name }}</span>
        </div>
        <div class="grid gap-3 p-5">
            <p class="text-sm font-semibold text-brand-700">{{ $tour->province->province_name }} · {{ $tour->duration_days }}N{{ $tour->duration_nights }}Đ</p>
            <h3 class="line-clamp-2 text-lg font-bold text-slate-900 group-hover:text-brand-700">{{ $tour->title }}</h3>
            @if ($tour->review_total > 0)
                <p class="text-sm font-semibold text-amber-600">★ {{ number_format($tour->average_rating, 1) }} <span class="font-normal text-slate-500">({{ $tour->review_total }} đánh giá)</span></p>
            @else
                <p class="text-sm text-slate-400">Chưa có đánh giá</p>
            @endif
            <div class="flex items-end justify-between gap-3 border-t border-slate-100 pt-3"><span class="text-sm text-slate-500">Từ</span><strong class="text-xl text-orange-600">{{ number_format($tour->min_price, 0, ',', '.') }}₫</strong></div>
        </div>
    </a>
</article>
