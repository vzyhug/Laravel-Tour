@extends('layouts.app')
@section('title', $destination->name.' · VietGo')
@section('content')
<section class="container-page py-10 sm:py-14">
    <a href="{{ route('destinations.index') }}" class="text-link">← Tất cả điểm đến</a>
    <p class="eyebrow mt-8">Miền {{ $destination->region }}</p>
    <h1 class="section-title mt-3">{{ $destination->name }}</h1>
    <p class="mt-5 max-w-3xl text-sm leading-7 text-muted">{{ $destination->description }}</p>
    <div class="mt-6 flex flex-wrap gap-5 text-sm"><a class="text-link" href="#tours">{{ $tours->count() }} tour</a><a class="text-link" href="#places">{{ $destination->places->count() }} địa danh</a><a class="text-link" href="#hotels">{{ $destination->hotels->count() }} khách sạn</a></div>
    <section id="tours" class="scroll-mt-24 pt-12"><h2 class="font-serif text-3xl">Tour khám phá {{ $destination->name }}</h2>
        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@forelse($tours as $tour)<x-tour-card :tour="$tour"/>@empty<p class="text-sm text-muted">Chương trình tour tại đây đang được bổ sung.</p>@endforelse</div>
    </section>
    <section id="places" class="scroll-mt-24 pt-12"><h2 class="font-serif text-3xl">Địa danh tham quan</h2><div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($destination->places as $record) @include('directory.card', ['kind' => 'places']) @empty <p class="text-muted">Đang bổ sung địa danh cho điểm đến này.</p> @endforelse
    </div></section>
    <section id="hotels" class="scroll-mt-24 pt-12"><h2 class="font-serif text-3xl">Khách sạn tại {{ $destination->name }}</h2><div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($destination->hotels as $record) @include('directory.card', ['kind' => 'hotels']) @empty <p class="text-sm text-muted">Danh mục khách sạn tại đây đang được bổ sung.</p> @endforelse
    </div></section>
</section>
@endsection
