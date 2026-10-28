@extends('layouts.app')
@section('title', $record->name.' · VietGo')
@section('content')
<section class="container-page py-10 sm:py-14">
    <a href="{{ route($kind.'.index') }}" class="text-link">← {{ $kind === 'hotels' ? 'Tất cả khách sạn' : 'Tất cả địa danh' }}</a>
    @php($hasDetails = $kind === 'hotels' || filled($record->address))
    <div class="mt-8 {{ $hasDetails ? 'grid gap-8 lg:grid-cols-[1.5fr_1fr]' : 'max-w-4xl' }}">
        <div>
            <h1 class="font-serif text-4xl leading-tight sm:text-5xl">{{ $record->name }}</h1>
            <div class="mt-4 flex flex-wrap gap-3 text-sm text-brand-600">
                @foreach($kind === 'hotels' ? collect([$record->destination]) : $record->destinations as $destination)<a class="underline" href="{{ route('destinations.show', $destination) }}">{{ $destination->name }}</a>@endforeach
            </div>
            <p class="mt-6 text-sm leading-8 text-muted">{{ $record->description }}</p>
            <x-image-gallery :images="$record->images" :name="$record->name"/>
        </div>
        @if($hasDetails)
        <aside class="h-fit rounded-2xl bg-linen p-6">
            <h2 class="font-serif text-2xl">Thông tin địa điểm</h2>
            @if($record->address)<p class="mt-5 text-sm leading-7"><strong>Địa chỉ:</strong> {{ $record->address }}</p><a class="text-link mt-3" href="{{ 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($record->name.' '.$record->address) }}" target="_blank" rel="noopener noreferrer">Tìm trên bản đồ ↗</a>@endif
            @if($kind === 'hotels')
                @if($record->phone)<p class="mt-5 text-sm"><strong>Điện thoại:</strong> <a class="underline" href="tel:{{ preg_replace('/[^+0-9]/', '', $record->phone) }}">{{ $record->phone }}</a></p>@endif
                <a class="btn-primary mt-6" href="{{ $record->website_url }}" target="_blank" rel="noopener noreferrer">Website khách sạn ↗</a>
                <p class="mt-4 text-xs leading-6 text-muted">Liên hệ trực tiếp khách sạn để xác nhận giá, phòng trống và dịch vụ cho ngày đi của bạn.</p>
            @endif
        </aside>
        @endif
    </div>
    @php($related = $kind === 'hotels' ? $places : $hotels)
    @if($related->isNotEmpty())
        <section class="mt-14"><h2 class="font-serif text-3xl">{{ $kind === 'hotels' ? 'Địa danh cùng điểm đến' : 'Khách sạn cùng điểm đến' }}</h2><div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($related as $relatedRecord) @include('directory.card', ['record' => $relatedRecord, 'kind' => $kind === 'hotels' ? 'places' : 'hotels']) @endforeach</div></section>
    @endif
</section>
@endsection
