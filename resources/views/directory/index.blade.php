@extends('layouts.app')
@section('title', ($kind === 'hotels' ? 'Khách sạn' : 'Địa danh tham quan').' · VietGo')
@section('content')
<section class="container-page py-10 sm:py-14">
    <a href="{{ route('destinations.index') }}" class="text-link">← Khám phá điểm đến</a>
    <p class="eyebrow mt-8">Cẩm nang Việt Nam</p>
    <h1 class="section-title mt-3">{{ $kind === 'hotels' ? 'Tìm nơi lưu trú cho hành trình.' : 'Khám phá những địa danh.' }}</h1>
    <form method="GET" class="mt-8 grid gap-4 rounded-2xl bg-linen p-5 sm:grid-cols-2 lg:grid-cols-4" role="search">
        <label class="text-sm">Tên hoặc địa chỉ<input name="q" maxlength="100" value="{{ request('q') }}" placeholder="Bạn muốn tìm gì?" class="mt-2 w-full rounded-lg border border-[#e7dcd1] bg-white p-3"></label>
        <label class="text-sm">Điểm đến<select name="destination" class="mt-2 w-full rounded-lg border border-[#e7dcd1] bg-white p-3"><option value="">Tất cả điểm đến</option>@foreach($destinations as $destination)<option value="{{ $destination->slug }}" @selected(request('destination') === $destination->slug)>{{ $destination->name }}</option>@endforeach</select></label>
        @if($categories)<label class="text-sm">Loại địa danh<select name="category" class="mt-2 w-full rounded-lg border border-[#e7dcd1] bg-white p-3"><option value="">Tất cả loại</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select></label>@endif
        <div class="flex items-end gap-4"><button class="btn-primary">Tìm kiếm</button><a class="py-3 text-sm underline" href="{{ route($kind.'.index') }}">Xóa lọc</a></div>
    </form>
    <p class="mt-6 text-sm text-muted">{{ $records->total() }} {{ $kind === 'hotels' ? 'khách sạn' : 'địa danh' }}</p>
    <div class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($records as $record) @include('directory.card') @empty <p class="col-span-full py-10 text-muted">Chưa tìm thấy kết quả phù hợp. Hãy thử bỏ bớt bộ lọc.</p> @endforelse
    </div>
    <div class="mt-8">{{ $records->links() }}</div>
</section>
@endsection
