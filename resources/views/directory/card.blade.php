@php
    $isHotel = $kind === 'hotels';
    $image = $record->images->first();
    $area = $isHotel ? $record->destination->name : $record->destinations->pluck('name')->join(' · ');
@endphp
<article class="overflow-hidden rounded-2xl border border-[#e7dcd1] bg-white">
    <a href="{{ route($kind.'.show', $record->slug) }}" class="group block">
        @if($image)
            <img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy" class="aspect-[4/3] w-full object-cover transition duration-300 group-hover:opacity-90">
        @else
            <div class="grid aspect-[4/3] place-items-center bg-linen text-sm text-muted">Đang bổ sung ảnh</div>
        @endif
        <div class="p-5">
            <p class="text-xs text-brand-600">{{ $area }}</p>
            <h3 class="mt-2 font-serif text-2xl">{{ $record->name }}</h3>
            @if(!$isHotel && $record->category)<p class="mt-2 text-xs text-muted">{{ \App\Models\ItineraryPlace::CATEGORIES[$record->category] ?? $record->category }}</p>@endif
            <p class="mt-3 line-clamp-3 text-sm leading-6 text-muted">{{ $record->description }}</p>
            <span class="text-link mt-4">Xem chi tiết <x-icon name="arrow" class="size-4"/></span>
        </div>
    </a>
</article>
