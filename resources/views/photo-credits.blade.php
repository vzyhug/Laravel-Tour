@extends('layouts.app')
@section('title', 'Nguồn ảnh địa danh · VietGo')
@section('content')
<div class="container-page py-12">
    <h1 class="font-serif text-4xl">Nguồn ảnh địa danh</h1>
    <p class="mt-4 text-muted">Ảnh được thu nhỏ để hiển thị trên website. Nguồn, tác giả và giấy phép của từng ảnh được giữ bên dưới.</p>
    <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($images as $image)
            <figure>
                <img src="{{ $image['url'] }}" alt="{{ $image['caption'] ?? $image['landmark'] ?? '' }}" loading="lazy" class="aspect-video w-full rounded-xl object-cover">
                <figcaption class="mt-3 text-sm">
                    <p>{{ $image['caption'] ?? $image['landmark'] ?? '' }}</p>
                    <p>{{ $image['author'] }}</p>
                    <a href="{{ $image['source_page'] }}" target="_blank" rel="noopener noreferrer" class="underline">Ảnh gốc và nguồn</a>
                    @if($image['license_url'])
                        · <a href="{{ $image['license_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $image['license'] }}</a>
                    @else
                        · {{ $image['license'] }}
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
</div>
@endsection
