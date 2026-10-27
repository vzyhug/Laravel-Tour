@if($schedule->stops->isNotEmpty())
<div class="mt-4 grid gap-4 sm:grid-cols-2">
@foreach($schedule->stops as $stop)
    @php($place = $stop->place)
    @php($image = $place->images->first())
    <figure class="overflow-hidden rounded-lg border border-[#e5d9cf] bg-white">
        @if($image)<img src="{{ asset($image->path) }}" alt="{{ $image->caption }}" class="aspect-[16/10] w-full object-cover" loading="lazy">@endif
        <figcaption class="p-3"><strong class="block text-xs text-ink">{{ $place->name }}</strong>
            <a href="{{ $place->source_url }}" target="_blank" rel="noopener noreferrer" class="text-[10px] underline">Nguồn địa danh</a>
            @if($image)<details class="mt-1 text-[10px] leading-5"><summary class="cursor-pointer">Nguồn ảnh & tác giả</summary><p>{{ $image->author }}</p><a href="{{ $image->source_url }}" class="underline" target="_blank" rel="noopener noreferrer">{{ $image->license }}</a></details>@endif
        </figcaption>
    </figure>
@endforeach
</div>
@endif
