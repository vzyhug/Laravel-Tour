@extends('layouts.admin')

@section('title', 'Điều hành chuyến đi')

@section('content')
    @php
        $salesStatuses = [
            'open' => ['Đang nhận khách', 'operations-status-open'],
            'full' => ['Đã đóng nhận khách', 'operations-status-closed'],
            'departed' => ['Đã khởi hành', 'operations-status-scheduled'],
            'ended' => ['Đã kết thúc', 'operations-status-completed'],
            'cancelled' => ['Đã hủy', 'operations-status-cancelled'],
        ];
        $operationStatuses = [
            'scheduled' => ['Sắp khởi hành', 'operations-status-scheduled'],
            'in_progress' => ['Đang diễn ra', 'operations-status-progress'],
            'ended' => ['Đã kết thúc', 'operations-status-completed'],
            'completed' => ['Đã hoàn thành', 'operations-status-completed'],
            'cancelled' => ['Đã hủy', 'operations-status-cancelled'],
        ];
    @endphp

    <section class="operations-heading">
        <div>
            <p class="admin-page-eyebrow">Trung tâm điều hành</p>
            <h1 class="admin-page-title">Chuyến đi của bạn</h1>
            <p class="mt-2 max-w-xl text-xs leading-6 text-[#78837b]">Theo dõi lịch khởi hành, tình trạng chuyến và mở danh sách hành khách để điểm danh hoặc gửi báo cáo.</p>
        </div>
        <div class="operations-heading-note">
            <span class="operations-heading-note-icon"><x-icon name="calendar" class="size-[18px]" /></span>
            <span><strong>{{ $departures->total() }}</strong><small>chuyến trong danh sách</small></span>
        </div>
    </section>

    <form method="GET" action="{{ route('operations.index') }}" data-auto-filter class="card mt-7 grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1.5fr)_minmax(150px,1fr)_minmax(150px,1fr)_minmax(160px,1fr)_auto] sm:p-5" aria-label="Lọc chuyến được phân công">
        <label class="grid gap-1.5 text-[10px] font-semibold text-muted sm:col-span-2 xl:col-span-1">
            Tìm tour
            <input class="field min-h-10" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nhập tên tour...">
        </label>
        <label class="grid gap-1.5 text-[10px] font-semibold text-muted">
            Khởi hành từ
            <input class="field min-h-10" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </label>
        <label class="grid gap-1.5 text-[10px] font-semibold text-muted">
            Khởi hành đến
            <input class="field min-h-10" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </label>
        <label class="grid gap-1.5 text-[10px] font-semibold text-muted">
            Trạng thái chuyến đi
            <select class="field min-h-10" name="trip_status">
                <option value="">Tất cả trạng thái</option>
                @foreach((session('employee_role') === 'Guide'
                    ? ['scheduled' => 'Sắp khởi hành', 'in_progress' => 'Đang đi', 'ended' => 'Đã kết thúc', 'cancelled' => 'Đã hủy']
                    : ['scheduled' => 'Sắp khởi hành', 'in_progress' => 'Đang diễn ra', 'ended' => 'Đã kết thúc', 'completed' => 'Đã hoàn thành', 'cancelled' => 'Đã hủy']) as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['trip_status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-1">
            <a class="btn-secondary min-h-10 px-3 text-xs" href="{{ route('operations.index') }}">Xóa bộ lọc</a>
        </div>
        <p class="text-[10px] text-muted sm:col-span-2 xl:col-span-full">Chọn ngày hoặc trạng thái để lọc ngay; tìm tên tour bằng Enter hoặc rời ô.</p>
    </form>

    <section class="mt-7" aria-label="Danh sách chuyến đi">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-[#2c342e]">Lịch điều hành</h2>
                <p class="mt-1 text-[10px] text-[#89928b]">Sắp xếp theo ngày khởi hành gần nhất.</p>
            </div>
            <p class="text-[10px] font-medium text-[#78837b]">Trang {{ $departures->currentPage() }} / {{ max(1, $departures->lastPage()) }}</p>
        </div>

        @if($departures->isNotEmpty())
            <div class="grid gap-3 xl:grid-cols-2">
                @foreach($departures as $departure)
                    @php
                        [$salesLabel, $salesClass] = $salesStatuses[$departure->effective_sales_status] ?? [$departure->status, 'operations-status-closed'];
                        [$operationLabel, $operationClass] = $operationStatuses[$departure->effective_trip_status] ?? [$departure->operation_status, 'operations-status-scheduled'];
                    @endphp
                    <a href="{{ route('operations.show', $departure) }}" class="operations-trip-card group">
                        <div class="operations-trip-date">
                            <span>THÁNG {{ $departure->departure_date->format('m') }}</span>
                            <strong>{{ $departure->departure_date->format('d') }}</strong>
                            <small>{{ $departure->departure_date->format('Y') }}</small>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="operations-status {{ $operationClass }}"><i></i>{{ $operationLabel }}</span>
                                @if($salesLabel !== $operationLabel)<span class="operations-status {{ $salesClass }}">{{ $salesLabel }}</span>@endif
                            </div>
                            <h3 class="mt-3 line-clamp-2 text-sm font-bold leading-5 text-[#29312c] transition group-hover:text-[#28745a]">{{ $departure->tour->title }}</h3>
                            <div class="mt-3 inline-flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700">
                                <x-icon name="users" class="size-4" />
                                {{ $departure->confirmed_passengers_count }} / {{ $departure->max_seats }} khách
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] text-[#78837b]">
                                <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="size-3.5" />{{ $departure->departure_date->format('d/m/Y') }} – {{ $departure->return_date->format('d/m/Y') }}</span>
                                <span class="inline-flex items-center gap-1.5"><x-icon name="user" class="size-3.5" />{{ $departure->guide?->full_name ?? 'Chưa phân công hướng dẫn viên' }}</span>
                            </div>
                        </div>
                        <span class="operations-trip-arrow" aria-hidden="true"><x-icon name="arrow" class="size-4" /></span>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $departures->links() }}</div>
        @else
            <div class="operations-empty-state">
                <span class="operations-empty-icon"><x-icon name="briefcase" class="size-6" /></span>
                <h2 class="mt-4 text-sm font-bold text-[#303832]">{{ collect($filters)->filter()->isNotEmpty() ? 'Không tìm thấy chuyến phù hợp' : 'Chưa có chuyến đi được phân công' }}</h2>
                <p class="mt-1 max-w-sm text-[11px] leading-5 text-[#818b83]">{{ collect($filters)->filter()->isNotEmpty() ? 'Thử thay đổi từ khóa, khoảng ngày hoặc trạng thái.' : 'Khi có chuyến mới được giao, thông tin lịch khởi hành sẽ hiển thị tại đây.' }}</p>
                @if(collect($filters)->filter()->isNotEmpty())<a href="{{ route('operations.index') }}" class="btn-secondary mt-4 min-h-10 px-4 text-xs">Xóa bộ lọc</a>@endif
            </div>
        @endif
    </section>
@endsection
