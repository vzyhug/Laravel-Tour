@extends('layouts.app')
@section('title', ($heading ?? 'Tour du lịch').' · VietGo')
@section('content')
@php($heroTour = collect($tours->items())->first(fn ($tour) => filled($tour->thumbnail_url)))
<section class="container-page pt-8 sm:pt-12">
    <div class="relative isolate overflow-hidden rounded-3xl bg-[#eee6dc] p-6 sm:p-10 lg:p-14">
        @if($heroTour)
            <div class="absolute inset-y-0 right-0 -z-20 hidden w-1/2 lg:block"><img src="{{ $heroTour->thumbnail_url }}" alt="" class="size-full object-cover"><div class="absolute inset-0 bg-gradient-to-r from-[#eee6dc] via-[#eee6dc]/15 to-transparent"></div></div>
        @endif
        <div class="max-w-2xl lg:max-w-[60%]"><p class="eyebrow">Bộ sưu tập hành trình · VietGo</p><h1 class="mt-4 font-serif text-4xl leading-tight sm:text-5xl">{{ $heading ?? 'Tìm miền cảm hứng của bạn.' }}</h1>
        @if($selectedProvince)<p class="mt-5 text-sm leading-7 text-muted">Tìm thấy <strong class="font-semibold text-ink">{{ $tours->total() }} hành trình</strong> tại {{ $selectedProvince->province_name }}.</p>@else<p class="mt-5 max-w-lg text-sm leading-7 text-muted">Những hành trình kết nối thiên nhiên, văn hóa và con người. Chọn điểm đến phù hợp, chúng tôi cùng bạn viết tiếp câu chuyện.</p>@endif
        <div class="mt-6 flex flex-wrap gap-2"><span class="rounded-full border border-[#d9caba] bg-white/70 px-4 py-2 text-[10px] font-semibold text-brand-700">{{ $tours->total() }} hành trình phù hợp</span><a href="{{ route('destinations.index') }}" class="rounded-full border border-[#d9caba] bg-white/70 px-4 py-2 text-[10px] font-semibold text-ink transition hover:bg-white">Khám phá theo điểm đến →</a></div></div>
    </div>
</section>
<section class="container-page grid items-start gap-8 py-10 lg:grid-cols-[250px_minmax(0,1fr)]">
    <aside class="sticky-search sticky-search-sidebar card p-5">
        <div class="flex items-center justify-between border-b border-[#e9dfd6] pb-4"><h2 class="font-serif text-xl">Chọn hành trình</h2><a href="{{ route('tours.index') }}" class="text-[10px] font-semibold text-brand-600">Xóa bộ lọc</a></div>
        <form action="{{ route('tours.index') }}" method="GET" id="tour-filters" class="mt-5 grid gap-5">
            <label><span class="label">Điểm đến / Từ khóa</span><input class="field" name="q" maxlength="200" value="{{ request('q') }}" placeholder="Bạn muốn khám phá đâu?"></label>
            <label><span class="label">Tỉnh thành</span><select class="field" name="province"><option value="">Mọi tỉnh thành</option>@foreach($provinces as $province)<option value="{{ $province->getKey() }}" data-region="{{ $province->region }}" @selected($selectedProvince?->getKey() === $province->getKey())>{{ $province->province_name }}</option>@endforeach</select></label>
            <div>
                <label><span class="label">Vùng miền</span><select class="field disabled:cursor-not-allowed disabled:bg-linen disabled:text-muted" name="region" aria-describedby="province-region-help" @disabled($selectedProvince)><option value="">Tất cả vùng miền</option>@foreach(['Bắc', 'Trung', 'Nam'] as $region)<option value="{{ $region }}" @selected(($selectedProvince?->region ?? request('region')) === $region)>Miền {{ $region }}</option>@endforeach</select></label>
                <p id="province-region-help" class="mt-2 text-[11px] leading-5 text-muted" @if(!$selectedProvince) hidden @endif>Miền tự theo tỉnh đã chọn. Bỏ chọn tỉnh để chọn miền khác.</p>
                @error('region')<p class="mt-2 text-xs text-red-700" data-region-error role="alert">{{ $message }}</p>@enderror
            </div>
            <label><span class="label">Cảm hứng trải nghiệm</span><select class="field" name="category"><option value="">Tất cả loại tour</option>@foreach($categories as $category)<option value="{{ $category->getKey() }}" @selected(request('category') == $category->getKey())>{{ $category->category_name }}</option>@endforeach</select></label>
            <fieldset class="grid gap-3">
                <legend class="label">Ngân sách / người lớn</legend>
                @foreach(['price_min' => 'Giá từ', 'price_max' => 'Giá đến'] as $field => $label)
                    <label>
                        <span class="label">{{ $label }} (₫)</span>
                        <input class="field" type="text" inputmode="numeric" data-money name="{{ $field }}" value="{{ old($field, request($field)) }}" placeholder="{{ $label }} (₫)" aria-describedby="{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}">
                        <span id="{{ $field }}-error" class="mt-2 block text-xs text-red-700" aria-live="polite" @if(!$errors->has($field)) hidden @endif>{{ $errors->first($field) }}</span>
                    </label>
                @endforeach
            </fieldset>
            <label><span class="label">Thời gian trải nghiệm</span><select class="field" name="days"><option value="">Không giới hạn</option>@foreach([2, 3, 4, 5] as $days)<option value="{{ $days }}" @selected(request('days') == $days)>Tối đa {{ $days }} ngày</option>@endforeach</select></label>
            <button class="btn-primary w-full"><x-icon name="search" class="size-4"/> Tìm hành trình</button>
        </form>
        <div class="mt-6 flex items-start gap-2 rounded-lg bg-sand p-3"><x-icon name="leaf" class="mt-0.5 size-4 shrink-0 text-sage"/><p class="text-[10px] leading-5 text-muted">Chậm lại một chút. Hành trình phù hợp đang chờ bạn.</p></div>
    </aside>
    <div class="min-w-0">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4"><p class="text-xs text-muted"><strong class="font-semibold text-ink">{{ $tours->total() }} hành trình</strong> đang chờ khám phá</p><label class="flex items-center gap-2 text-[10px] text-muted"><span>Sắp xếp</span><select name="sort" form="tour-filters" class="min-h-11 rounded-lg border border-[#e5d9cf] bg-white px-3 text-xs" data-sort-tours><option value="">Được tuyển chọn</option><option value="price_asc" @selected(($sort ?? request('sort')) === 'price_asc')>Giá thấp đến cao</option><option value="price_desc" @selected(($sort ?? request('sort')) === 'price_desc')>Giá cao đến thấp</option></select></label></div>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">@forelse($tours as $tour)<x-tour-card :tour="$tour"/>@empty<div class="card col-span-full px-6 py-16 text-center"><span class="mx-auto grid size-14 place-items-center rounded-full bg-linen text-brand-600"><x-icon name="search" class="size-6"/></span><h2 class="mt-5 font-serif text-2xl">Chưa tìm thấy hành trình phù hợp.</h2><p class="mt-3 text-sm text-muted">Thử thay đổi điểm đến, ngân sách hoặc thời gian nhé.</p><a href="{{ route('tours.index') }}" class="btn-secondary mt-6">Khám phá tất cả</a></div>@endforelse</div>
        <div class="mt-10">{{ $tours->links() }}</div>
    </div>
</section>
@endsection
