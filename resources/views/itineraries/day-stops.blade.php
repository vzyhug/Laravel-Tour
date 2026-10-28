@if($schedule->stops->isNotEmpty())
<div class="mt-4 grid gap-4 sm:grid-cols-2" data-image-gallery>
@foreach($schedule->stops as $stop)
    @php($place = $stop->place)
    @php($image = $place->images->first())
    <figure class="overflow-hidden rounded-lg border border-[#e5d9cf] bg-white">
        @if($image)<a href="{{ $image->url }}" target="_blank" rel="noopener" data-gallery-item aria-label="Phóng to ảnh: {{ $place->name }}"><img src="{{ asset($image->path) }}" alt="{{ $image->caption }}" class="aspect-[16/10] w-full object-cover" loading="lazy"></a>@endif
        <figcaption class="p-3"><strong class="block text-xs text-ink">{{ $place->name }}</strong>
        </figcaption>
    </figure>
@endforeach
</div>
@endif
