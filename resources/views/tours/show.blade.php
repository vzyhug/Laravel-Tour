@extends('layouts.app')
@section('title', $tour->title.' · VietGo')
@section('content')
<section class="container-page py-8">
    <nav class="text-sm text-slate-500" aria-label="Breadcrumb"><a class="hover:text-brand-700" href="{{ route('tours.index') }}">Tour</a> / {{ $tour->province->province_name }}</nav>
    <div class="mt-5 grid gap-5 lg:grid-cols-[1.5fr_1fr]">
        <div class="aspect-[16/9] overflow-hidden rounded-3xl bg-gradient-to-br from-brand-100 via-cyan-100 to-orange-100">@if($tour->thumbnail_url)<img class="size-full object-cover" src="{{ $tour->thumbnail_url }}" alt="{{ $tour->title }}">@endif</div>
        <div class="grid grid-cols-2 gap-4">@foreach($tour->images->where('is_thumbnail', false)->take(4) as $image)<img class="size-full min-h-32 rounded-2xl object-cover" src="{{ $image->img_url }}" alt="Ảnh {{ $tour->title }}" loading="lazy">@endforeach</div>
    </div>
</section>
<section class="container-page grid gap-10 pb-16 lg:grid-cols-[1fr_380px]">
    <article>
        <div class="flex flex-wrap gap-2"><span class="badge">{{ $tour->category->category_name }}</span><span class="badge">{{ $tour->province->province_name }}</span></div>
        <h1 class="mt-4 text-3xl font-black text-ink sm:text-4xl">{{ $tour->title }}</h1>
        <div class="mt-5 grid gap-3 rounded-2xl bg-white p-5 sm:grid-cols-3"><div><span class="text-sm text-slate-500">Thời gian</span><p class="font-bold">{{ $tour->duration_days }} ngày {{ $tour->duration_nights }} đêm</p></div><div><span class="text-sm text-slate-500">Khởi hành</span><p class="font-bold">{{ $tour->pickup_point ?: 'Theo lịch' }}</p></div><div><span class="text-sm text-slate-500">Đánh giá</span><p class="font-bold text-amber-600">★ {{ number_format($tour->reviews->avg('rating') ?: 0, 1) }} ({{ $tour->reviews->count() }})</p></div></div>
        <div class="mt-10"><h2 class="text-2xl font-black text-ink">Giới thiệu</h2><p class="mt-4 whitespace-pre-line leading-8 text-slate-600">{{ $tour->description }}</p></div>
        <div class="mt-10"><h2 class="text-2xl font-black text-ink">Lịch trình</h2><div class="mt-5 grid gap-4">@forelse($tour->schedules as $schedule)<div class="card flex gap-4 p-5"><span class="grid size-11 shrink-0 place-items-center rounded-full bg-brand-600 font-black text-white">{{ $schedule->day_number }}</span><div><h3 class="font-bold">{{ $schedule->title ?: 'Ngày '.$schedule->day_number }}</h3><p class="mt-2 leading-7 text-slate-600">{{ $schedule->activities }}</p></div></div>@empty<p class="leading-8 text-slate-600">{{ $tour->itinerary_summary }}</p>@endforelse</div></div>
        <div class="mt-10"><h2 class="text-2xl font-black text-ink">Dịch vụ bao gồm</h2><p class="mt-4 whitespace-pre-line leading-8 text-slate-600">{{ $tour->included_services }}</p></div>
        <div class="mt-10"><h2 class="text-2xl font-black text-ink">Đánh giá</h2><div class="mt-5 grid gap-4">@forelse($tour->reviews as $review)<div class="card p-5"><div class="flex justify-between gap-3"><strong>{{ $review->customer->full_name }}</strong><span class="text-amber-500">{{ str_repeat('★', $review->rating) }}</span></div><p class="mt-2 text-slate-600">{{ $review->feedback }}</p></div>@empty<p class="text-slate-500">Chưa có đánh giá.</p>@endforelse</div></div>
    </article>
    <aside><div class="card sticky top-24 p-6"><p class="text-sm text-slate-500">Giá từ</p><p class="mt-1 text-3xl font-black text-orange-600">{{ number_format($tour->min_price, 0, ',', '.') }}₫</p><h2 class="mt-6 font-bold">Chọn lịch khởi hành</h2><div class="mt-3 grid gap-3">@forelse($tour->departures as $departure)<a class="rounded-xl border border-slate-200 p-4 transition hover:border-brand-500 hover:bg-brand-50" href="{{ route('bookings.create', $departure) }}"><div class="flex justify-between gap-3"><strong>{{ $departure->departure_date->format('d/m/Y') }}</strong><span class="text-sm text-brand-700">Còn {{ $departure->available_seats }} chỗ</span></div><p class="mt-1 text-sm text-slate-500">{{ number_format($departure->adult_price, 0, ',', '.') }}₫ / người lớn</p></a>@empty<p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Chưa có lịch khởi hành sắp tới.</p>@endforelse</div></div></aside>
</section>
@endsection
