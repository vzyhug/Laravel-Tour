@extends('layouts.admin')

@section('title', 'Lịch khởi hành')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-semibold text-brand-700">Điều hành</p>
            <h1 class="text-3xl font-black text-ink">Lịch khởi hành</h1>
        </div>
        <a class="btn-primary" href="{{ route('admin.departures.create') }}">+ Thêm lịch</a>
    </div>

    <form method="GET" action="{{ route('admin.departures.index') }}" data-auto-filter class="card mt-6 grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1.5fr)_minmax(145px,1fr)_minmax(145px,1fr)_minmax(180px,1.2fr)_minmax(150px,1fr)_auto] sm:p-5" aria-label="Lọc lịch khởi hành">
        <label class="grid gap-1.5 text-xs font-semibold text-muted sm:col-span-2 xl:col-span-1">
            Tìm tour
            <input class="field min-h-10" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nhập tên tour...">
        </label>
        <label class="grid gap-1.5 text-xs font-semibold text-muted">
            Khởi hành từ
            <input class="field min-h-10" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </label>
        <label class="grid gap-1.5 text-xs font-semibold text-muted">
            Khởi hành đến
            <input class="field min-h-10" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </label>
        <label class="grid gap-1.5 text-xs font-semibold text-muted">
            Hướng dẫn viên
            <select class="field min-h-10" name="guide_id">
                <option value="">Tất cả hướng dẫn viên</option>
                @foreach($guides as $guide)
                    <option value="{{ $guide->getKey() }}" @selected((string) ($filters['guide_id'] ?? '') === (string) $guide->getKey())>{{ $guide->full_name }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1.5 text-xs font-semibold text-muted">
            Trạng thái chuyến đi
            <select class="field min-h-10" name="trip_status">
                <option value="">Tất cả trạng thái</option>
                @foreach(['scheduled' => 'Sắp khởi hành', 'in_progress' => 'Đang diễn ra', 'ended' => 'Đã kết thúc', 'completed' => 'Đã hoàn thành', 'cancelled' => 'Đã hủy'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['trip_status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-1">
            <a class="btn-secondary min-h-10 px-3 text-xs" href="{{ route('admin.departures.index') }}">Xóa bộ lọc</a>
        </div>
        <p class="text-[10px] text-muted sm:col-span-2 xl:col-span-full">Chọn ngày, hướng dẫn viên hoặc trạng thái để lọc ngay; tìm tên tour bằng Enter hoặc rời ô.</p>
    </form>

    <div class="card mt-8 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-225 text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="p-4">Tour</th>
                        <th class="p-4">Khởi hành</th>
                        <th class="p-4">Chỗ trống</th>
                        <th class="p-4">Hướng dẫn viên</th>
                        <th class="p-4">Tiến độ chuyến</th>
                        <th class="p-4 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php($tripStates = ['scheduled' => ['Sắp khởi hành', 'bg-sky-50 text-sky-700'], 'in_progress' => ['Đang diễn ra', 'bg-emerald-50 text-emerald-700'], 'ended' => ['Đã kết thúc', 'bg-stone-100 text-stone-700'], 'completed' => ['Đã hoàn thành', 'bg-emerald-50 text-emerald-700'], 'cancelled' => ['Đã hủy', 'bg-rose-50 text-rose-700']])
                    @php($salesStates = ['open' => ['Đang nhận khách', 'text-emerald-700'], 'full' => ['Đã đóng nhận khách', 'text-amber-800'], 'departed' => ['Đã khởi hành', 'text-sky-700'], 'ended' => ['Đã kết thúc', 'text-stone-600'], 'cancelled' => ['Đã hủy', 'text-rose-700']])
                    @forelse($departures as $departure)
                        @php([$tripLabel, $tripTone] = $tripStates[$departure->effective_trip_status] ?? [$departure->operation_status, 'bg-stone-100 text-stone-700'])
                        @php([$salesLabel, $salesTone] = $salesStates[$departure->effective_sales_status] ?? [$departure->status, 'text-stone-600'])
                        <tr>
                            <td class="p-4 font-semibold">{{ $departure->tour->title }}</td>
                            <td class="p-4">
                                {{ $departure->departure_date->format('d/m/Y') }} → {{ $departure->return_date->format('d/m/Y') }}
                                <p class="text-xs text-slate-500">
                                    Giờ tập trung: {{ $departure->time_label ?? 'Chưa có giờ' }}
                                </p>
                            </td>
                            <td class="p-4">{{ $departure->available_seats }}/{{ $departure->max_seats }}</td>
                            <td class="p-4">{{ $departure->guide?->full_name ?? 'Chưa phân công' }}</td>
                            <td class="p-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold {{ $tripTone }}">{{ $tripLabel }}</span>@if($salesLabel !== $tripLabel)<span class="mt-1 block text-[10px] font-medium {{ $salesTone }}">{{ $salesLabel }}</span>@endif</td>
                            <td class="p-4">
                                <div class="flex justify-end gap-2">
                                    <a class="font-semibold text-brand-700"
                                       href="{{ route('admin.departures.edit', $departure) }}">Sửa</a>
                                    @if($departure->status !== 'cancelled')
                                        <form method="POST" action="{{ route('admin.departures.destroy', $departure) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="font-semibold text-red-600"
                                                    onclick="return confirm('Hủy lịch này?')">Hủy</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center">
                            <p class="font-semibold text-ink">{{ collect($filters)->filter()->isNotEmpty() ? 'Không tìm thấy lịch khởi hành phù hợp' : 'Chưa có lịch khởi hành' }}</p>
                            <p class="mt-1 text-xs text-muted">{{ collect($filters)->filter()->isNotEmpty() ? 'Thử thay đổi bộ lọc rồi tìm lại.' : 'Thêm lịch mới để bắt đầu quản lý chuyến.' }}</p>
                            @if(collect($filters)->filter()->isNotEmpty())<a class="btn-secondary mt-4 min-h-9 px-3 text-xs" href="{{ route('admin.departures.index') }}">Xóa bộ lọc</a>@endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $departures->links() }}</div>
@endsection
