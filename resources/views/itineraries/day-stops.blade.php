@if($schedule->stops->isNotEmpty())
<div class="mt-4 grid gap-4 sm:grid-cols-2" data-day-place-list>
@foreach($schedule->stops as $stop)
    @php($place = $stop->place)
    @php($image = $place->images->first())
    <figure class="overflow-hidden rounded-lg border border-[#e5d9cf] bg-white" data-itinerary-place data-place-name="{{ $place->name }}">
        @if($image)<img src="{{ asset($image->path) }}" alt="{{ $image->caption }}" class="aspect-[16/10] w-full object-cover" loading="lazy">@endif
        <figcaption class="flex items-center justify-between gap-3 p-3"><strong class="block text-xs text-ink">{{ $place->name }}</strong><button type="button" class="place-drag-handle" data-place-drag-handle aria-label="K&#xE9;o &#x111;&#x1EC3; s&#x1EAF;p x&#x1EBF;p {{ $place->name }}" title="K&#xE9;o &#x111;&#x1EC3; &#x111;&#x1ED5;i th&#x1EE9; t&#x1EF1; &#x111;i&#x1EC3;m">&#x28FF;</button>
        </figcaption>
    </figure>
@endforeach
</div>
@endif
