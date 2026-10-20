@extends('layouts.app')
@section('title', ($heading ?? 'Tour du lịch') . ' · VietGo')
@section('content')
    <section class="border-b bg-white py-12">
        <div class="container-page">
            <p class="font-semibold text-brand-700">Khám phá</p>
            <h1 class="mt-1 text-4xl font-black text-ink">{{ $heading ?? 'Tất cả tour du lịch' }}</h1>
        </div>
    </section>
    <section class="container-page py-10">
        <form method="GET" class="card grid gap-4 p-5 md:grid-cols-2 lg:grid-cols-4">
            <label class="lg:col-span-2">
                <span class="label">Từ khóa</span>
                <input class="field" name="q" value="{{ request('q') }}" placeholder="Tên tour...">
            </label>
            <label>
                <span class="label">Miền</span>
                <select class="field" name="region">
                    <option value="">Tất cả</option>
                    @foreach (['Bắc', 'Trung', 'Nam'] as $region)
                        <option @selected(request('region') === $region)>{{ $region }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Loại tour</span>
                <select class="field" name="category">
                    <option value="">Tất cả</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->getKey() }}" @selected(request('category') == $category->getKey())>{{ $category->category_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Giá từ (₫)</span>
                <input class="field" type="text" inputmode="numeric" data-money name="price_min" value="{{ request('price_min') !== null && request('price_min') !== '' ? number_format((int) preg_replace('/\D/', '', request('price_min')), 0, ',', '.') : '' }}" placeholder="VD: 2.000.000">
            </label>
            <label>
                <span class="label">Giá đến (₫)</span>
                <input class="field" type="text" inputmode="numeric" data-money name="price_max" value="{{ request('price_max') !== null && request('price_max') !== '' ? number_format((int) preg_replace('/\D/', '', request('price_max')), 0, ',', '.') : '' }}" placeholder="VD: 5.000.000">
            </label>
            <label>
                <span class="label">Số ngày tối đa</span>
                <select class="field" name="days">
                    <option value="">Tất cả</option>
                    @foreach ([2, 3, 4, 5] as $d)
                        <option value="{{ $d }}" @selected(request('days') == $d)>Tối đa {{ $d }} ngày</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Sắp xếp</span>
                <select class="field" name="sort">
                    <option value="">Mặc định</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Giá thấp đến cao</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Giá cao đến thấp</option>
                </select>
            </label>
            <button class="btn-primary self-end">Lọc tour</button>
        </form>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($tours as $tour)
                <x-tour-card :tour="$tour" />
            @empty
                <div class="card col-span-full p-12 text-center">
                    <h2 class="text-xl font-bold">Không tìm thấy tour</h2>
                    <p class="mt-2 text-slate-500">Hãy thử thay đổi bộ lọc.</p>
                </div>
            @endforelse
        </div>
        <div class="mt-10">{{ $tours->links() }}</div>
    </section>
    @push('scripts')
<script>
    document.querySelectorAll('[data-money]').forEach(function (input) {
        input.addEventListener('input', function () {
            var digits = input.value.replace(/\D/g, '');
            input.value = digits === '' ? '' : Number(digits).toLocaleString('vi-VN');
        });
    });
</script>
@endpush
@endsection
