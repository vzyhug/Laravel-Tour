@php
    $isHotel = $kind === 'hotels';
    $image = $record->images->first();
    $area = $isHotel ? $record->destination->name : $record->destinations->pluck('name')->join(' · ');
    $roomRate = $isHotel ? $record->roomTypes->pluck('min_nightly_price')->filter()->min() : null;
@endphp
@if($isHotel)
    <article class="hotel-card group">
        <a href="{{ route($kind.'.show', $record->slug) }}" class="block">
            <div class="hotel-card-image">
                @if($image)<img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy">@else<div class="grid size-full place-items-center text-sm text-muted">Ảnh đang được bổ sung</div>@endif
                <span class="hotel-card-photo-count"><x-icon name="map" class="size-3.5"/> {{ $area }}</span>
                @if($roomRate)<span class="hotel-card-photo-price">Từ {{ number_format((float) $roomRate, 0, ',', '.') }}₫ / đêm</span>@else<span class="hotel-card-photo-price">Xem thông tin lưu trú</span>@endif
                <span class="hotel-card-arrow" aria-hidden="true"><x-icon name="arrow-up" class="size-4"/></span>
            </div>
            <div class="flex min-h-52 flex-col p-5 sm:p-6">
                <p class="eyebrow text-[9px]">VietGo stay <span class="px-1 text-[#ccb6a8]">/</span> {{ $area }}</p>
                <h3 class="mt-2 line-clamp-2 font-serif text-[23px] leading-snug transition group-hover:text-brand-600">{{ $record->name }}</h3>
                @if($record->address)<p class="mt-2 flex items-start gap-2 text-[11px] leading-5 text-muted"><x-icon name="pin" class="mt-0.5 size-3.5 shrink-0"/><span class="line-clamp-1">{{ $record->address }}</span></p>@endif
                <p class="mt-3 line-clamp-2 text-xs leading-6 text-muted">{{ $record->description }}</p>
                <div class="mt-auto flex items-center justify-between gap-3 border-t border-[#eee5dd] pt-4">
                    <span class="text-[10px] font-medium text-sage">Ảnh & thông tin khách sạn</span>
                    <span class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-linen px-3 text-[10px] font-semibold text-brand-700 transition group-hover:bg-brand-600 group-hover:text-white">Khám phá <x-icon name="arrow" class="size-3.5"/></span>
                </div>
            </div>
        </a>
    </article>
@else
    <article class="overflow-hidden rounded-2xl border border-[#e7dcd1] bg-white transition duration-300 hover:-translate-y-1 hover:shadow-lg">
        <a href="{{ route($kind.'.show', $record->slug) }}" class="group block">
            @if($image)<img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy" class="aspect-[4/3] w-full object-cover transition duration-700 group-hover:scale-[1.03]">@else<div class="grid aspect-[4/3] place-items-center bg-linen text-sm text-muted">Ảnh đang được bổ sung</div>@endif
            <div class="p-5"><p class="text-xs text-brand-600">{{ $area }}</p><h3 class="mt-2 font-serif text-2xl">{{ $record->name }}</h3>
                @if($record->category)<p class="mt-2 text-xs text-muted">{{ \App\Models\ItineraryPlace::CATEGORIES[$record->category] ?? $record->category }}</p>@endif
                <p class="mt-3 line-clamp-3 text-sm leading-6 text-muted">{{ $record->description }}</p><span class="text-link mt-4">Xem chi tiết <x-icon name="arrow" class="size-4"/></span>
            </div>
        </a>
    </article>
@endif
