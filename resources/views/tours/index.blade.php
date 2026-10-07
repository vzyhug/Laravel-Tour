@extends('layouts.app')
@section('title', ($heading ?? 'Tour du lịch').' · VietGo')
@section('content')
<section class="border-b bg-white py-12"><div class="container-page"><p class="font-semibold text-brand-700">Khám phá</p><h1 class="mt-1 text-4xl font-black text-ink">{{ $heading ?? 'Tất cả tour du lịch' }}</h1></div></section>
<section class="container-page py-10">
    <form method="GET" class="card grid gap-4 p-5 md:grid-cols-[1fr_180px_180px_auto]">
        <label><span class="label">Từ khóa</span><input class="field" name="q" value="{{ request('q') }}" placeholder="Tên tour..."></label>
        <label><span class="label">Miền</span><select class="field" name="region"><option value="">Tất cả</option>@foreach(['Bắc','Trung','Nam'] as $region)<option @selected(request('region') === $region)>{{ $region }}</option>@endforeach</select></label>
        <label><span class="label">Loại tour</span><select class="field" name="category"><option value="">Tất cả</option>@foreach($categories as $category)<option value="{{ $category->getKey() }}" @selected(request('category') == $category->getKey())>{{ $category->category_name }}</option>@endforeach</select></label>
        <button class="btn-primary self-end">Lọc tour</button>
    </form>
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@forelse($tours as $tour)<x-tour-card :tour="$tour" />@empty<div class="card col-span-full p-12 text-center"><h2 class="text-xl font-bold">Không tìm thấy tour</h2><p class="mt-2 text-slate-500">Hãy thử thay đổi bộ lọc.</p></div>@endforelse</div>
    <div class="mt-10">{{ $tours->links() }}</div>
</section>
@endsection
