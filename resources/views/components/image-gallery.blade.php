@props(['images', 'name'])
@if($images->isNotEmpty())
<div class="mt-8" data-image-gallery>
    <div class="mb-4 flex items-center justify-between gap-3"><h2 class="font-serif text-2xl">Hình ảnh {{ $name }}</h2><span class="shrink-0 text-xs text-muted">{{ $images->count() }} ảnh</span></div>
    <div class="grid grid-cols-2 gap-3">
        @foreach($images as $image)
            <a href="{{ $image->url }}" target="_blank" rel="noopener" data-gallery-item aria-label="Phóng to ảnh: {{ $image->caption }}" @class(['group relative block overflow-hidden rounded-2xl bg-linen focus-visible:ring-2 focus-visible:ring-brand-500', 'col-span-full' => $loop->first])>
                <img src="{{ $image->url }}" alt="{{ $image->caption }}" @class(['w-full transition duration-300 group-hover:scale-[1.02]', 'max-h-[600px] object-contain' => $loop->first, 'aspect-[4/3] object-cover' => !$loop->first]) @if(!$loop->first) loading="lazy" @endif>
                <span class="absolute bottom-3 right-3 flex items-center gap-2 rounded-full bg-white/95 px-3 py-2 text-xs text-ink"><x-icon name="search" class="size-4"/><span>{{ $loop->first ? 'Xem ảnh lớn' : 'Phóng to' }}</span></span>
            </a>
        @endforeach
    </div>
</div>
@else
<div class="mt-8 rounded-2xl bg-linen p-10 text-sm text-muted">Ảnh địa điểm đang được bổ sung.</div>
@endif
