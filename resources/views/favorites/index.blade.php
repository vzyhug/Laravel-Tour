@extends('layouts.app')
@section('title', 'Tour yêu thích · VietGo')
@section('content')
    <section class="container-page pt-8 sm:pt-12">
        <x-account-navigation/>
        <x-page-intro title="Để dành một ngày lên đường." eyebrow="Bộ sưu tập · Tour yêu thích" icon="bookmark" description="Những hành trình khiến bạn muốn xách ba lô lên. Lưu lại hôm nay, chọn một ngày đẹp trời để bắt đầu."/>
    </section>
    <section class="container-page py-10">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($tours as $tour)
                <x-tour-card :tour="$tour" />
            @empty
                <div class="card col-span-full p-12 text-center">
                    <x-icon name="bookmark" class="mx-auto size-10 text-brand-600"/>
                    <h2 class="mt-5 font-serif text-3xl">Bộ sưu tập đang chờ cảm hứng đầu tiên.</h2>
                    <p class="mt-3 text-sm leading-7 text-muted">Bấm nút lưu trên thẻ tour để giữ lại hành trình bạn yêu thích.</p>
                    <a href="{{ route('tours.index') }}" class="btn-primary mt-6">Khám phá các hành trình</a>
                </div>
            @endforelse
        </div>
    </section>
@endsection
