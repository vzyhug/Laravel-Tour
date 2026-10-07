@extends('layouts.app')
@section('title', 'VietGo · Khám phá Việt Nam')
@section('content')
<section class="relative overflow-hidden bg-ink py-20 text-white sm:py-28">
    <div class="absolute inset-0 opacity-30 [background:radial-gradient(circle_at_20%_20%,#2dd4bf_0,transparent_35%),radial-gradient(circle_at_80%_60%,#fb923c_0,transparent_30%)]"></div>
    <div class="container-page relative">
        <span class="badge bg-white/10 text-teal-100">Hành trình được tuyển chọn</span>
        <h1 class="mt-6 max-w-4xl text-4xl font-black leading-tight tracking-tight sm:text-6xl">Chạm vào vẻ đẹp<br><span class="text-teal-300">khắp Việt Nam.</span></h1>
        <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-300">Tìm tour phù hợp, chọn lịch khởi hành và giữ chỗ chỉ trong vài phút.</p>
        <form action="{{ route('home') }}" method="GET" class="mt-10 grid max-w-4xl gap-3 rounded-2xl bg-white p-3 text-slate-900 shadow-2xl sm:grid-cols-[1fr_220px_auto]" aria-label="Tìm kiếm tour">
            <label><span class="sr-only">Điểm đến</span><input class="field border-0 bg-slate-50" name="q" value="{{ request('q') }}" placeholder="Bạn muốn đi đâu?"></label>
            <label><span class="sr-only">Tỉnh thành</span><select class="field border-0 bg-slate-50" name="province"><option value="">Mọi tỉnh thành</option>@foreach($provinces as $province)<option value="{{ $province->getKey() }}" @selected(request('province') == $province->getKey())>{{ $province->province_name }}</option>@endforeach</select></label>
            <button class="btn-primary">Tìm tour</button>
        </form>
    </div>
</section>

<section class="container-page py-16">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="font-semibold text-brand-700">Được yêu thích</p><h2 class="mt-1 text-3xl font-black text-ink">Tour nổi bật</h2></div><a class="font-semibold text-brand-700 hover:underline" href="{{ route('tours.index') }}">Xem tất cả →</a></div>
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">@forelse($tours as $tour)<x-tour-card :tour="$tour" />@empty<p class="col-span-full rounded-2xl bg-white p-10 text-center text-slate-500">Chưa có tour phù hợp.</p>@endforelse</div>
</section>

<section class="container-page pb-16">
    <div class="card grid overflow-hidden lg:grid-cols-2">
        <div class="bg-brand-600 p-8 text-white sm:p-12"><p class="text-sm font-bold uppercase tracking-widest text-teal-100">Vì sao chọn VietGo?</p><h2 class="mt-3 text-3xl font-black">Đi xa hơn, an tâm hơn.</h2><p class="mt-4 leading-7 text-teal-50">Lịch trình minh bạch, giá rõ ràng và đội ngũ hỗ trợ trong suốt chuyến đi.</p></div>
        <div class="grid gap-6 p-8 sm:grid-cols-2 sm:p-12">@foreach([['✓','Giá minh bạch'],['◎','Lịch trình chọn lọc'],['♢','Thanh toán an toàn'],['☎','Hỗ trợ tận tâm']] as [$icon,$label])<div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-xl bg-brand-50 font-bold text-brand-700">{{ $icon }}</span><strong>{{ $label }}</strong></div>@endforeach</div>
    </div>
</section>
@endsection
