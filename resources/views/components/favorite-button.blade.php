@props(['tour', 'compact' => false])
@php
    $saved = session('customer_id') && \Illuminate\Support\Facades\DB::table('favorites')
        ->where('customer_id', session('customer_id'))->where('tour_id', $tour->getKey())->exists();
@endphp
@if(session('customer_id'))
    <form method="POST" action="{{ route('favorites.toggle', $tour) }}">
        @csrf
        <button type="submit" @class(['favorite-button', 'favorite-button--compact' => $compact])
            aria-label="{{ $saved ? 'Bỏ lưu tour' : 'Lưu tour yêu thích' }}" aria-pressed="{{ $saved ? 'true' : 'false' }}"
            title="{{ $saved ? 'Đã lưu vào tour yêu thích · Bấm để bỏ lưu' : 'Lưu vào tour yêu thích' }}">
            <x-icon name="bookmark" class="favorite-button__icon"/>
            @if(!$compact || $saved)<span>{{ $saved ? 'Đã lưu' : 'Lưu tour' }}</span>@endif
        </button>
    </form>
@else
    <a href="{{ route('login') }}" @class(['favorite-button', 'favorite-button--compact' => $compact]) aria-label="Đăng nhập để lưu tour" title="Đăng nhập để lưu tour">
        <x-icon name="bookmark" class="favorite-button__icon"/>
        @if(!$compact)<span>Lưu tour</span>@endif
    </a>
@endif
