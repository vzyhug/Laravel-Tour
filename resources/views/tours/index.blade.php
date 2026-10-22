@extends('layouts.app')
@section('title', ($heading ?? 'Tour du lịch').' · VietGo')
@section('content')
<section class="border-b border-[#d4dce2] bg-linen/45 py-12 sm:py-16">
    <div class="container-page"><p class="eyebrow">Bộ sưu tập hành trình · VietGo</p><h1 class="section-title mt-3 sm:text-5xl">{{ $heading ?? 'Tìm miền cảm hứng của bạn.' }}</h1><p class="mt-4 max-w-2xl text-sm leading-7 text-muted">Những hành trình kết nối thiên nhiên, văn hóa và con người. Chọn điểm đến phù hợp, chúng tôi cùng bạn viết tiếp câu chuyện.</p></div>
</section>
<section class="container-page grid items-start gap-8 py-10 lg:grid-cols-[250px_minmax(0,1fr)]">
    <aside class="card p-5 lg:sticky lg:top-28">
        <div class="flex items-center justify-between border-b border-[#d4dce2] pb-4"><h2 class="font-serif text-xl">Chọn hành trình</h2><a href="{{ url()->current() }}" class="text-[10px] font-semibold text-brand-600">Xóa bộ lọc</a></div>
        <form method="GET" id="tour-filters" class="mt-5 grid gap-5">
            @if(request('province'))<input type="hidden" name="province" value="{{ request('province') }}">@endif
            <label><span class="label">Ngày khởi hành</span><input class="field" type="date" name="departure_date" value="{{ old('departure_date', request('departure_date')) }}" min="{{ today()->format('Y-m-d') }}">@error('departure_date')<span class="mt-2 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
            <label><span class="label">Số người tham gia</span><select class="field" name="guests"><option value="">Chưa chọn</option>@foreach(range(1, 20) as $guest)<option value="{{ $guest }}" @selected(request('guests') == $guest)>{{ $guest }} người</option>@endforeach</select></label>
            <label><span class="label">Điểm đến / Từ khóa</span><input class="field" name="q" value="{{ request('q') }}" placeholder="Bạn muốn khám phá đâu?"></label>
            <label><span class="label">Vùng miền</span><select class="field" name="region"><option value="">Tất cả vùng miền</option>@foreach(['Bắc', 'Trung', 'Nam'] as $region)<option value="{{ $region }}" @selected(request('region') === $region)>Miền {{ $region }}</option>@endforeach</select></label>
            <label><span class="label">Cảm hứng trải nghiệm</span><select class="field" name="category"><option value="">Tất cả loại tour</option>@foreach($categories as $category)<option value="{{ $category->getKey() }}" @selected(request('category') == $category->getKey())>{{ $category->category_name }}</option>@endforeach</select></label>
            <fieldset class="grid gap-3"><legend class="label">Ngân sách / người lớn</legend><label><span class="sr-only">Giá từ (₫)</span><input class="field" type="text" inputmode="numeric" data-money name="price_min" value="{{ old('price_min', request('price_min')) }}" placeholder="Từ (₫)">@error('price_min')<span class="mt-2 block text-xs text-red-700">{{ $message }}</span>@enderror</label><label><span class="sr-only">Giá đến (₫)</span><input class="field" type="text" inputmode="numeric" data-money name="price_max" value="{{ old('price_max', request('price_max')) }}" placeholder="Đến (₫)">@error('price_max')<span class="mt-2 block text-xs text-red-700">{{ $message }}</span>@enderror</label></fieldset>
            <label><span class="label">Thời gian trải nghiệm</span><select class="field" name="days"><option value="">Không giới hạn</option>@foreach([2, 3, 4, 5] as $days)<option value="{{ $days }}" @selected(request('days') == $days)>Tối đa {{ $days }} ngày</option>@endforeach</select></label>
            <button class="btn-primary w-full"><x-icon name="search" class="size-4"/> Tìm hành trình</button>
        </form>
        <div class="mt-6 flex items-start gap-2 rounded-lg bg-sand p-3"><x-icon name="leaf" class="mt-0.5 size-4 shrink-0 text-sage"/><p class="text-[10px] leading-5 text-muted">Chậm lại một chút. Hành trình phù hợp đang chờ bạn.</p></div>
    </aside>
    <div class="min-w-0">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4"><p class="text-xs text-muted"><strong class="font-semibold text-ink">{{ $tours->total() }} hành trình</strong> đang chờ khám phá</p><label class="flex items-center gap-2 text-[10px] text-muted"><span>Sắp xếp</span><select name="sort" form="tour-filters" class="min-h-11 rounded-lg border border-[#d4dce2] bg-white px-3 text-xs" data-sort-tours><option value="">Được tuyển chọn</option><option value="price_asc" @selected(($sort ?? request('sort')) === 'price_asc')>Giá thấp đến cao</option><option value="price_desc" @selected(($sort ?? request('sort')) === 'price_desc')>Giá cao đến thấp</option></select></label></div>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">@forelse($tours as $tour)<x-tour-card :tour="$tour"/>@empty<div class="card col-span-full px-6 py-16 text-center"><span class="mx-auto grid size-14 place-items-center rounded-full bg-linen text-brand-600"><x-icon name="search" class="size-6"/></span><h2 class="mt-5 font-serif text-2xl">Chưa tìm thấy hành trình phù hợp.</h2><p class="mt-3 text-sm text-muted">Thử thay đổi điểm đến, ngân sách hoặc thời gian nhé.</p><a href="{{ url()->current() }}" class="btn-secondary mt-6">Khám phá tất cả</a></div>@endforelse</div>
        <div class="mt-10">{{ $tours->links() }}</div>
    </div>
</section>
@endsection
