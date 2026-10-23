@extends('layouts.app')
@section('title', $service->label().' · VietGo')
@section('content')
@include('platform.nav')
<section class="bg-brand-50 py-10"><div class="container-page"><p class="eyebrow">VietGo · {{ $service->label() }}</p><h1 class="section-title mt-3">Tìm {{ mb_strtolower($service->label()) }} cho chuyến đi của bạn</h1><p class="mt-4 text-sm leading-7 text-muted">Nhà cung cấp, giá và ảnh minh họa là dữ liệu demo. Báo giá sẽ kiểm tra tồn cho ngày, nhóm khách và gói cụ thể.</p>
    <form method="GET" class="card mt-6 grid items-end gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        <label><span class="label">Địa điểm / từ khóa</span><input class="field" name="q" value="{{ request('q') }}" placeholder="Đà Nẵng, Hà Nội..."></label>
        @if($service === \App\Enums\ServiceType::Stay)
            <label><span class="label">Nhận phòng</span><input class="field" type="date" name="check_in" min="{{ today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ request('check_in') }}"></label>
            <label><span class="label">Trả phòng</span><input class="field" type="date" name="check_out" value="{{ request('check_out') }}"></label>
            <label><span class="label">Số phòng</span><input class="field" type="number" name="rooms" min="1" max="5" value="{{ request('rooms', 1) }}"></label>
            <label><span class="label">Người lớn</span><input class="field" type="number" name="adults" min="1" max="20" value="{{ request('adults', 1) }}"></label>
            <label><span class="label">Trẻ em</span><input class="field" type="number" name="children" min="0" max="10" value="{{ request('children', 0) }}"></label>
        @elseif($service === \App\Enums\ServiceType::Bus)
            <label><span class="label">Điểm đi</span><input class="field" name="origin" value="{{ request('origin') }}" placeholder="Đà Nẵng"></label>
            <label><span class="label">Điểm đến</span><input class="field" name="destination" value="{{ request('destination') }}" placeholder="Huế"></label>
            <label><span class="label">Ngày đi</span><input class="field" type="date" name="date" min="{{ today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ request('date') }}"></label>
            <label><span class="label">Số người / ghế</span><input class="field" type="number" name="passengers" min="1" max="8" value="{{ request('passengers',1) }}"></label>
            <p class="text-xs leading-6 text-muted">Chọn chuyến, đúng điểm đón/trả và ghế trong trang chi tiết. Chuyến qua đêm hiển thị cả ngày đến.</p>
        @elseif($service === \App\Enums\ServiceType::Rental)
            <label><span class="label">Nhận xe (UTC+7)</span><input class="field" type="datetime-local" name="pickup_at" value="{{ request('pickup_at') }}"></label>
            <label><span class="label">Trả xe (UTC+7)</span><input class="field" type="datetime-local" name="return_at" value="{{ request('return_at') }}"></label>
            <label><span class="label">Số người</span><input class="field" type="number" name="passengers" min="1" max="16" value="{{ request('passengers', 1) }}"></label>
        @elseif($service === \App\Enums\ServiceType::Activity)
            <label><span class="label">Ngày sử dụng</span><input class="field" type="date" name="visit_date" min="{{ today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ request('visit_date') }}"></label>
            <label><span class="label">Người lớn</span><input class="field" type="number" name="adults" min="1" max="20" value="{{ request('adults', 1) }}"></label>
            <label><span class="label">Trẻ em</span><input class="field" type="number" name="children" min="0" max="20" value="{{ request('children', 0) }}"></label>
        @else
            <p class="text-sm leading-7 text-muted sm:col-span-2">Chọn sân bay/hạng xe để nhập chiều đi, giờ đón, vùng phục vụ và địa chỉ. Giá cuối dựa trên gói và vùng; chưa có geocoding thật.</p>
        @endif
        <label><span class="label">Sắp xếp giá</span><select class="field" name="sort"><option value="price_asc" @selected(request('sort') !== 'price_desc')>Thấp đến cao</option><option value="price_desc" @selected(request('sort') === 'price_desc')>Cao đến thấp</option></select></label>
        <button class="btn-primary">Tìm kiếm</button><a class="text-link" href="{{ route('platform.index', $service->value) }}">Xóa bộ lọc</a>
    </form>
</div></section>
<section class="container-page py-10"><p class="mb-5 text-sm text-muted">{{ $offers->count() }} lựa chọn · {{ $service->unit() }} · VND</p><div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @forelse($offers as $offer) @php($product = $offer['product'])
        <article class="card overflow-hidden"><a href="{{ route('platform.products.show', ['product' => $product, ...request()->query()]) }}"><img src="{{ $product->image_url }}" alt="Ảnh địa danh minh họa cho {{ $product->name }}" class="aspect-[16/10] w-full object-cover" loading="lazy"></a><div class="p-5"><span class="badge">{{ $product->type_label }} · Demo</span><h2 class="mt-3 text-xl leading-7"><a href="{{ route('platform.products.show', ['product' => $product, ...request()->query()]) }}">{{ $product->name }}</a></h2><p class="mt-3 text-xs text-muted">{{ $product->location }}</p>@if(($product->attributes['stars'] ?? 0) > 0)<p class="mt-2 text-xs text-muted">Hạng lưu trú demo: {{ $product->attributes['stars'] }} sao · Không phải điểm đánh giá</p>@endif<p class="mt-4 text-xs text-muted">{{ $offer['is_total'] ? 'Tổng cho cấu hình tìm kiếm, từ' : 'Giá từ / '.$service->unit() }}</p><strong class="price-text mt-1 block text-2xl">{{ number_format($offer['price'], 0, ',', '.') }}₫</strong><a class="btn-primary mt-5 w-full" href="{{ route('platform.products.show', ['product' => $product, ...request()->query()]) }}">Xem gói & lịch</a></div></article>
    @empty
        <div class="card p-8 sm:col-span-2 lg:col-span-3"><h2 class="text-xl">Không có lựa chọn phù hợp</h2><p class="mt-3 text-sm leading-7 text-muted">Thử địa điểm, thời gian hoặc nhóm khách khác. Một đêm/khung giờ đóng bán có thể khiến toàn bộ khoảng chọn không khả dụng.</p></div>
    @endforelse
</div></section>
@endsection
