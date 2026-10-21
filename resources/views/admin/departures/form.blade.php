@extends('layouts.admin')
@section('title',$departure->exists ? 'Sửa lịch' : 'Thêm lịch')
@section('content')
<a class="font-semibold text-brand-700" href="{{ route('admin.departures.index') }}">← Lịch khởi hành</a><h1 class="mt-3 text-3xl font-black text-ink">{{ $departure->exists ? 'Chỉnh sửa lịch' : 'Thêm lịch khởi hành' }}</h1><form class="card mt-7 grid gap-5 p-6 md:grid-cols-2" method="POST" action="{{ $departure->exists ? route('admin.departures.update',$departure) : route('admin.departures.store') }}">@csrf @if($departure->exists)@method('PUT')@endif
<label class="md:col-span-2">
    <span class="label">Tour</span>
    <select class="field @error('tour_id') border-red-500 @enderror" name="tour_id">
        @foreach($tours as $tour)
            <option value="{{ $tour->getKey() }}" data-days="{{ $tour->duration_days }}" @selected(old('tour_id',$departure->tour_id)==$tour->getKey())>{{ $tour->title }} ({{ $tour->duration_days }} ngày)</option>
        @endforeach
    </select>
    @error('tour_id') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
</label>
<label>
    <span class="label">Ngày khởi hành</span>
    <input class="field @error('departure_date') border-red-500 @enderror" type="date" name="departure_date" value="{{ old('departure_date',$departure->departure_date?->format('Y-m-d')) }}" required>
    @error('departure_date') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
</label>
<label>
    <span class="label">Ngày về</span>
    <input class="field @error('return_date') border-red-500 @enderror" type="date" name="return_date" value="{{ old('return_date',$departure->return_date?->format('Y-m-d')) }}" required>
    @error('return_date') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
</label>
<label>
    <span class="label">Tổng số chỗ</span>
    <input class="field @error('max_seats') border-red-500 @enderror" type="number" name="max_seats" value="{{ old('max_seats',$departure->max_seats ?? 20) }}" required>
    @error('max_seats') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
</label>
<label>
    <span class="label">Chỗ còn lại</span>
    <input class="field @error('available_seats') border-red-500 @enderror" type="number" name="available_seats" value="{{ old('available_seats',$departure->available_seats ?? 20) }}" required>
    @error('available_seats') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
</label>
<label>
    <span class="label">Giá người lớn</span>
    <input class="field @error('adult_price') border-red-500 @enderror" type="number" name="adult_price" value="{{ old('adult_price',$departure->adult_price) }}" required>
    @error('adult_price') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
</label>
<label>
    <span class="label">Giá trẻ em</span>
    <input class="field @error('child_price') border-red-500 @enderror" type="number" name="child_price" value="{{ old('child_price',$departure->child_price) }}" required>
    @error('child_price') <span class="text-xs font-semibold text-red-600">{{ $message }}</span> @enderror
</label>
<label>
    <span class="label">Hướng dẫn viên</span>
    <select class="field" name="guide_id"><option value="">Chưa phân công</option>@foreach($guides as $guide)<option value="{{ $guide->getKey() }}" @selected(old('guide_id',$departure->guide_id)==$guide->getKey())>{{ $guide->full_name }}</option>@endforeach</select>
</label>
<label>
    <span class="label">Trạng thái</span>
    <select class="field" name="status">@foreach(['open'=>'Mở bán','full'=>'Đã đầy','cancelled'=>'Đã hủy'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$departure->status)===$value)>{{ $label }}</option>@endforeach</select>
</label>
<div class="md:col-span-2"><button class="btn-primary">Lưu lịch khởi hành</button></div></form>
@endsection
