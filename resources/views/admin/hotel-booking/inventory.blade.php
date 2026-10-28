@extends('layouts.admin')

@section('title', 'Tồn phòng và giá')

@section('content')
    <a href="{{ route('admin.hotel-booking.index') }}" class="mb-5 inline-flex min-h-9 items-center gap-2 rounded-lg px-3 text-[10px] font-semibold text-[#657068] transition hover:bg-white hover:text-[#28745a]"><x-icon name="arrow" class="size-3.5 rotate-180" /> Quay lại đơn lưu trú</a>

    <section class="admin-page-heading">
        <div>
            <p class="admin-page-eyebrow">Cấu hình phòng khách sạn</p>
            <h1 class="admin-page-title">{{ $type->hotel->name }}</h1>
            <p class="mt-2 text-xs text-[#78837b]">{{ $type->name }} <span class="px-1 text-[#c2c9c3]">·</span> Tối đa {{ $type->max_guests }} khách / phòng</p>
        </div>
        @if($type->is_demo)<span class="hotel-source-demo">Dữ liệu minh họa</span>@endif
    </section>

    <section class="mt-6 grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-page-eyebrow">Lịch mở bán</p><h2 class="mt-1 text-sm font-bold text-[#2c342e]">Tồn phòng và giá theo đêm</h2></div><span class="hotel-count-chip">{{ $nights->total() }} ngày đã cấu hình</span></div>
            <div class="overflow-x-auto">
                <table class="admin-data-table">
                    <thead><tr><th>Ngày lưu trú</th><th>Phòng trống</th><th>Tổng phòng</th><th class="text-right">Giá / phòng / đêm</th></tr></thead>
                    <tbody>
                        @forelse($nights as $night)
                            <tr><td class="font-medium text-[#303832]">{{ $night->stay_date->format('d/m/Y') }}</td><td><span class="hotel-count-chip">{{ $night->available_rooms }} phòng</span></td><td class="text-[#657068]">{{ $night->total_rooms }}</td><td class="whitespace-nowrap text-right font-semibold text-[#303832]">{{ number_format($night->nightly_price, 0, ',', '.') }}₫</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-[11px] text-[#89928b]">Chưa có ngày lưu trú nào được mở bán.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 sm:px-6">{{ $nights->links() }}</div>
        </div>

        <aside class="hotel-create-panel">
            <span class="hotel-create-icon"><x-icon name="calendar" class="size-5" /></span>
            <p class="admin-page-eyebrow mt-4">Mở bán theo ngày</p>
            <h2 class="mt-1 text-base font-bold text-[#29312c]">Cập nhật tồn và giá</h2>
            <p class="mt-1 text-[10px] leading-5 text-[#78837b]">Nhập theo từng đêm. Ngày trả phòng không chiếm tồn; ngày chưa nhập sẽ không mở bán.</p>
            <form method="POST" action="{{ route('admin.hotel-booking.inventory.save', $type) }}" class="mt-5 grid gap-3">
                @csrf @method('PUT')
                <label class="hotel-form-label">Đêm lưu trú<input class="hotel-form-control" type="date" name="stay_date" min="{{ today()->toDateString() }}" required></label>
                <label class="hotel-form-label">Tổng số phòng<input class="hotel-form-control" type="number" name="total_rooms" min="0" max="10000" placeholder="Ví dụ: 12" required></label>
                <label class="hotel-form-label">Giá mỗi phòng / đêm (VND)<span class="relative block"><input class="hotel-form-control pr-12" type="number" name="nightly_price" min="1" max="1000000000" placeholder="Ví dụ: 1500000" required><span class="hotel-input-suffix">VND</span></span></label>
                <button class="mt-1 inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-[#28745a] px-4 text-[10px] font-semibold text-white transition hover:bg-[#205d48]"><x-icon name="check" class="size-4" /> Lưu tồn và giá</button>
            </form>
        </aside>
    </section>
@endsection
