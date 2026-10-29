@extends('layouts.admin')
@section('title','Sửa địa điểm')
@section('content')
<div><p class="font-semibold text-brand-700">Danh mục</p><h1 class="text-3xl font-black text-ink">Sửa tỉnh / thành</h1></div>
<form class="card mt-8 grid max-w-xl gap-5 p-6" method="POST" action="{{ route('admin.provinces.update', $province) }}">
    @csrf
    @method('PUT')
    <label><span class="label">Tên tỉnh / thành</span>
        <input class="field" name="province_name" value="{{ old('province_name', $province->province_name) }}" required>
    </label>
    <label><span class="label">Miền</span>
        <select class="field" name="region">
            @foreach(['Bắc','Trung','Nam'] as $region)
                <option @selected(old('region', $province->region) === $region)>{{ $region }}</option>
            @endforeach
        </select>
    </label>
    <div class="flex gap-3">
        <button class="btn-primary">Lưu thay đổi</button>
        <a class="inline-flex items-center rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-100" href="{{ route('admin.provinces.index') }}">Hủy</a>
    </div>
</form>
@endsection
