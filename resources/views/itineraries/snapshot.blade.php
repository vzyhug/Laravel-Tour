@php($places = collect($consultation->snapshot['places'])->keyBy('id'))
<div class="mt-5 grid gap-5" data-image-gallery>
@foreach($consultation->snapshot['days'] as $day)
    <section class="rounded-xl border border-[#e5d9cf] bg-white p-5">
        <h3 class="font-serif text-xl">Ngày {{ $day['number'] }} · {{ $day['title'] }}</h3>
        @forelse(collect($consultation->snapshot['items'])->where('day_number', $day['number']) as $item)
            @php($place = isset($item['place_id']) ? $places->get($item['place_id']) : null)
            <div class="mt-4 border-t border-[#e5d9cf] pt-4">
                <strong class="text-sm">{{ $item['title'] }}</strong>
                <p class="mt-2 text-xs text-muted">{{ $item['start_time'] ?: 'Chưa chọn giờ' }} @if($item['duration_minutes']) · {{ $item['duration_minutes'] }} phút @endif</p>
                @if($place && ($place['image'] ?? null))<a href="{{ $place['image']['url'] }}" target="_blank" rel="noopener" data-gallery-item aria-label="Phóng to ảnh: {{ $place['name'] }}" class="mt-3 block max-w-lg"><img src="{{ $place['image']['url'] }}" alt="{{ $place['image']['caption'] }}" loading="lazy" class="aspect-[16/8] w-full rounded-lg object-cover"></a>@endif
                @if($item['notes'])<p class="mt-3 whitespace-pre-line text-sm leading-7">{{ $item['notes'] }}</p>@endif
            </div>
        @empty<p class="mt-3 text-sm text-muted">Khách để trống ngày này.</p>@endforelse
    </section>
@endforeach
</div>
