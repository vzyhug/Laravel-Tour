<section class="relative isolate overflow-hidden bg-[#082c5c] text-white">
    <img src="{{ asset('images/tours/demo/da-nang-2.jpg') }}" alt="Khám phá biển và những điểm đến Việt Nam" class="absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#082c5c]/95 via-[#082c5c]/70 to-[#082c5c]/20"></div>
    <div class="container-page py-12 sm:py-20">
        <span class="inline-flex rounded-full bg-white/15 px-4 py-2 text-xs font-semibold">VietGo · Khám phá và đặt tour tại Việt Nam</span>
        <h1 class="mt-6 max-w-3xl text-4xl leading-tight sm:text-5xl">Chuyến đi tiếp theo,<br>niềm vui đang chờ bạn.</h1>
        <p class="mt-5 max-w-xl text-sm leading-7 text-white/90">Tìm lịch khởi hành, so sánh giá và chọn hành trình phù hợp. Mọi chi phí và điều kiện được hiển thị trước khi đặt.</p>
        <div class="mt-9 rounded-2xl bg-white p-5 text-ink shadow-xl sm:p-7">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4"><span class="flex items-center gap-2 font-bold text-brand-600"><x-icon name="map" class="size-5"/> Tour & trải nghiệm</span><a href="#services" class="text-xs font-semibold text-brand-600">Các dịch vụ của VietGo <span aria-hidden="true">→</span></a></div>
            <form action="{{ route('tours.index') }}" method="GET" class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_auto]" aria-label="Tìm kiếm tour">
                <label><span class="label">Điểm đến / trải nghiệm</span><input class="field" name="q" value="{{ request('q') }}" placeholder="Bạn muốn đi đâu?"></label>
                <label><span class="label">Ngày khởi hành</span><input type="date" class="field" name="departure_date" min="{{ today()->format('Y-m-d') }}" value="{{ request('departure_date') }}"></label>
                <label><span class="label">Số người tham gia</span><select class="field" name="guests">@foreach(range(1, 20) as $guest)<option value="{{ $guest }}" @selected(request('guests', 1) == $guest)>{{ $guest }} người</option>@endforeach</select></label>
                <button class="btn-primary"><x-icon name="search" class="size-4"/> Tìm tour</button>
            </form>
            <p class="mt-4 text-xs leading-6 text-muted">Giá từ / người lớn · VND · Giờ Việt Nam (UTC+7). Chọn lịch và nhóm khách để xem tổng tiền.</p>
        </div>
    </div>
</section>
<section id="services" class="container-page py-10">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <a href="{{ route('tours.index') }}" class="flex items-center gap-3 rounded-xl border border-brand-500 bg-brand-50 p-4 font-semibold text-brand-700"><x-icon name="map" class="size-6 shrink-0"/><span>Tour & trải nghiệm<span class="mt-1 block text-xs font-normal">Đang mở bán demo</span></span></a>
        @foreach(['Lưu trú', 'Xe khách', 'Đưa đón sân bay', 'Thuê xe'] as $service)
            <div class="rounded-xl border border-slate-200 bg-linen p-4"><p class="text-sm font-semibold">{{ $service }}</p><p class="mt-2 text-xs text-muted">Chưa mở bán</p></div>
        @endforeach
    </div>
</section>
@if($coupons->isNotEmpty())
<section id="offers" class="container-page pb-10">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl">Ưu đãi cho chuyến đi</h2><span class="text-xs text-muted">Điều kiện và lượt dùng được kiểm tra khi đặt</span></div>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">@foreach($coupons as $coupon)<article class="rounded-xl border border-dashed border-brand-500 bg-brand-50 p-5"><p class="text-lg font-bold text-brand-700">Giảm {{ (float) $coupon->discount_percent }}%</p><p class="mt-2 text-xs leading-6 text-muted">Đơn từ {{ number_format($coupon->min_order, 0, ',', '.') }}₫@if($coupon->max_discount) · Giảm tối đa {{ number_format($coupon->max_discount, 0, ',', '.') }}₫@endif</p><div class="mt-4 flex flex-wrap items-center justify-between gap-2"><code class="font-bold">{{ $coupon->code }}</code><button type="button" class="text-xs font-bold text-brand-600" data-copy-coupon="{{ $coupon->code }}">Sao chép mã</button></div><p class="mt-2 text-xs text-muted">Sao chép chưa đồng nghĩa đã áp dụng.</p></article>@endforeach</div>
    <p data-copy-status class="mt-3 text-sm text-brand-700" role="status"></p>
</section>
@endif
