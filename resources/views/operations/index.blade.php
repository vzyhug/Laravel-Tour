@extends('layouts.admin')

@section('title', 'Điều hành chuyến đi')

@section('content')
    @php
        $salesStatuses = [
            'open' => ['Đang nhận khách', 'operations-status-open'],
            'full' => ['Đã đóng nhận khách', 'operations-status-closed'],
            'cancelled' => ['Đã hủy', 'operations-status-cancelled'],
        ];
        $operationStatuses = [
            'scheduled' => ['Sắp khởi hành', 'operations-status-scheduled'],
            'in_progress' => ['Đang diễn ra', 'operations-status-progress'],
            'completed' => ['Đã hoàn thành', 'operations-status-completed'],
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
                        [$salesLabel, $salesClass] = $salesStatuses[$departure->status] ?? [$departure->status, 'operations-status-closed'];
                        [$operationLabel, $operationClass] = $operationStatuses[$departure->operation_status] ?? [$departure->operation_status, 'operations-status-scheduled'];
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
                                <span class="operations-status {{ $salesClass }}">{{ $salesLabel }}</span>
                            </div>
                            <h3 class="mt-3 line-clamp-2 text-sm font-bold leading-5 text-[#29312c] transition group-hover:text-[#28745a]">{{ $departure->tour->title }}</h3>
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
                <h2 class="mt-4 text-sm font-bold text-[#303832]">Chưa có chuyến đi được phân công</h2>
                <p class="mt-1 max-w-sm text-[11px] leading-5 text-[#818b83]">Khi có chuyến mới được giao, thông tin lịch khởi hành sẽ hiển thị tại đây.</p>
            </div>
        @endif
    </section>
@endsection
