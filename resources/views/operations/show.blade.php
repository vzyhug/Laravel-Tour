@extends('layouts.admin')

@section('title', 'Chi tiết chuyến đi')

@section('content')
    @php
        $salesStatuses = [
            'open' => ['Đang nhận khách', 'bg-emerald-50 text-emerald-700'],
            'full' => [$departure->operation_status === 'scheduled' ? 'Đã ngừng nhận khách' : 'Đã đóng nhận khách', 'bg-amber-50 text-amber-800'],
            'cancelled' => ['Đã hủy', 'bg-rose-50 text-rose-700'],
        ];
        $operationStatuses = [
            'scheduled' => ['Sắp khởi hành', 'bg-sky-50 text-sky-700'],
            'in_progress' => ['Đang diễn ra', 'bg-emerald-50 text-emerald-700'],
            'completed' => ['Đã hoàn thành', 'bg-stone-100 text-stone-700'],
        ];
        [$salesLabel, $salesTone] = $salesStatuses[$departure->status] ?? [$departure->status, 'bg-stone-100 text-stone-700'];
        [$operationLabel, $operationTone] = $operationStatuses[$departure->operation_status] ?? [$departure->operation_status, 'bg-stone-100 text-stone-700'];
        $passengerCount = $departure->bookings->sum(fn ($booking) => $booking->passengers->count());
        $presentCount = $attendance->where('status', 'present')->count();
        $absentCount = $attendance->where('status', 'absent')->count();
        $canEditAttendance = $departure->status !== 'cancelled'
            && $departure->operation_status !== 'completed'
            && ! $departure->departure_date->isFuture();
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <a class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50" href="{{ route('operations.index') }}">
            <span aria-hidden="true">←</span> Danh sách chuyến
        </a>

        <section class="overflow-hidden rounded-3xl border border-[#eadfd4] bg-[#fffdf9] shadow-[0_8px_32px_rgba(56,38,25,.05)]">
            <div class="grid gap-6 p-5 sm:p-7 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-center lg:p-9">
                <div class="min-w-0">
                    <p class="text-[10px] font-extrabold uppercase tracking-[.18em] text-brand-700">Chuyến được phân công</p>
                    <h1 class="mt-2 font-serif text-3xl font-medium leading-tight text-ink sm:text-4xl">{{ $departure->tour->title }}</h1>
                    <div class="mt-5 flex flex-wrap gap-x-5 gap-y-3 text-sm text-muted">
                        <span class="inline-flex items-center gap-2"><x-icon name="calendar" class="size-4 text-brand-600" />{{ $departure->departure_date->format('d/m/Y') }} <span class="text-[#b8a99d]">→</span> {{ $departure->return_date->format('d/m/Y') }}</span>
                        <span class="inline-flex items-center gap-2"><x-icon name="user" class="size-4 text-brand-600" />{{ $departure->guide?->full_name ?? 'Chưa phân công' }}</span>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold {{ $operationTone }}"><i class="size-1.5 rounded-full bg-current"></i>{{ $operationLabel }}</span>
                        <span class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-bold {{ $salesTone }}">{{ $salesLabel }}</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-[#eadfd4] bg-[#fff8f3] p-5">
                    <p class="text-[10px] font-extrabold uppercase tracking-[.15em] text-muted">Điều hành chuyến</p>
                    @if($departure->status !== 'cancelled' && $departure->operation_status !== 'completed')
                        <form method="POST" action="{{ route('operations.status', $departure) }}" class="mt-3">
                            @csrf @method('PATCH')
                            <input type="hidden" name="operation_status" value="{{ $departure->operation_status === 'scheduled' ? 'in_progress' : 'completed' }}">
                            <button class="btn-primary w-full justify-center">
                                {{ $departure->operation_status === 'scheduled' ? 'Bắt đầu chuyến' : 'Hoàn thành chuyến' }}
                                <x-icon name="arrow" class="size-4" />
                            </button>
                        </form>
                        <p class="mt-3 text-xs leading-5 text-muted">
                            @if($departure->operation_status === 'scheduled')
                                Bắt đầu khi đến ngày khởi hành.
                            @else
                                Hoàn thành sau ngày kết thúc và khi đã điểm danh đủ hành khách.
                            @endif
                        </p>
                    @elseif($departure->status === 'cancelled')
                        <p class="mt-3 rounded-xl bg-rose-50 p-3 text-xs font-semibold leading-5 text-rose-800">Chuyến đã hủy. Các thao tác điều hành đã đóng.</p>
                    @else
                        <p class="mt-3 rounded-xl bg-emerald-50 p-3 text-xs font-semibold leading-5 text-emerald-800">Chuyến đã hoàn thành.</p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 border-t border-[#eadfd4] bg-[#fcf7f3] sm:grid-cols-4">
                <div class="border-b border-[#eadfd4] px-5 py-4 sm:border-b-0 sm:px-7">
                    <p class="text-[10px] font-semibold text-muted">Hành khách</p><p class="mt-1 text-xl font-extrabold text-ink">{{ $passengerCount }}</p>
                </div>
                <div class="border-b border-l border-[#eadfd4] px-5 py-4 sm:border-b-0 sm:px-7">
                    <p class="text-[10px] font-semibold text-muted">Có mặt</p><p class="mt-1 text-xl font-extrabold text-emerald-700">{{ $presentCount }}</p>
                </div>
                <div class="px-5 py-4 sm:border-l sm:border-[#eadfd4] sm:px-7">
                    <p class="text-[10px] font-semibold text-muted">Vắng mặt</p><p class="mt-1 text-xl font-extrabold text-rose-700">{{ $absentCount }}</p>
                </div>
                <div class="border-l border-[#eadfd4] px-5 py-4 sm:px-7">
                    <p class="text-[10px] font-semibold text-muted">Đơn đặt tour</p><p class="mt-1 text-xl font-extrabold text-ink">{{ $departure->bookings->count() }}</p>
                </div>
            </div>
        </section>

        <section aria-labelledby="attendance-heading" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-[.16em] text-brand-700">Danh sách đoàn</p>
                    <h2 id="attendance-heading" class="mt-1 font-serif text-2xl font-medium text-ink">Hành khách & điểm danh</h2>
                    <p class="mt-1 text-xs leading-5 text-muted">Chỉ hiển thị khách có đơn đã xác nhận hoặc hoàn thành.</p>
                </div>
                @if($departure->departure_date->isFuture() && $departure->status !== 'cancelled' && $departure->operation_status !== 'completed')
                    <p class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900"><x-icon name="clock" class="size-4" /> Điểm danh mở vào ngày khởi hành</p>
                @endif
            </div>

            @forelse($departure->bookings as $booking)
                <article class="overflow-hidden rounded-2xl border border-[#eadfd4] bg-[#fffdf9] shadow-[0_3px_14px_rgba(56,38,25,.035)]">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-[#f0e7df] px-4 py-4 sm:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-[#f3ede7] px-2 py-1 font-mono text-[10px] font-bold text-[#6b5142]">{{ $booking->booking_code }}</span>
                                <h3 class="truncate text-sm font-bold text-ink">{{ $booking->customer->full_name }}</h3>
                            </div>
                            @if($booking->customer->phone)
                                <a href="tel:{{ $booking->customer->phone }}" class="mt-1.5 inline-flex items-center gap-1.5 text-xs text-muted transition hover:text-brand-700"><x-icon name="phone" class="size-3.5" />{{ $booking->customer->phone }}</a>
                            @endif
                        </div>
                        <span class="rounded-full bg-[#fcf0e9] px-3 py-1.5 text-[10px] font-bold text-brand-700">{{ $booking->passengers->count() }} khách</span>
                    </header>

                    <div class="divide-y divide-[#f0e7df] px-4 sm:px-6">
                        @foreach($booking->passengers as $passenger)
                            @php($record = $attendance->get($passenger->getKey()))
                            <div class="grid gap-3 py-4 lg:grid-cols-[minmax(150px,.8fr)_minmax(100px,.5fr)_minmax(0,1.7fr)] lg:items-center">
                                <div>
                                    <p class="text-sm font-semibold text-ink">{{ $passenger->full_name }}</p>
                                    @if($record?->checked_at)<p class="mt-1 text-[10px] text-muted">Cập nhật {{ $record->checked_at->format('d/m/Y H:i') }}</p>@endif
                                </div>
                                <span @class([
                                    'inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1.5 text-[10px] font-bold',
                                    'bg-emerald-50 text-emerald-700' => $record?->status === 'present',
                                    'bg-rose-50 text-rose-700' => $record?->status === 'absent',
                                    'bg-amber-50 text-amber-800' => ! $record,
                                ])><i class="size-1.5 rounded-full bg-current"></i>{{ $record ? ($record->status === 'present' ? 'Có mặt' : 'Vắng mặt') : 'Chưa điểm danh' }}</span>

                                @if($canEditAttendance && $booking->status === 'confirmed')
                                    <form method="POST" action="{{ route('operations.attendance', [$departure, $passenger]) }}" class="grid gap-2 sm:grid-cols-[150px_minmax(0,1fr)_auto] sm:items-end">
                                        @csrf @method('PUT')
                                        <label class="grid gap-1 text-[10px] font-semibold text-muted">Trạng thái
                                            <select name="status" class="input min-h-10 w-full text-xs"><option value="present" @selected($record?->status === 'present')>Có mặt</option><option value="absent" @selected($record?->status === 'absent')>Vắng mặt</option></select>
                                        </label>
                                        <label class="grid gap-1 text-[10px] font-semibold text-muted">Ghi chú
                                            <input class="input min-h-10 w-full text-xs" name="notes" maxlength="500" value="{{ $record?->notes }}" placeholder="Thêm ghi chú nếu cần">
                                        </label>
                                        <button class="btn-primary min-h-10 justify-center px-4 text-xs">Lưu điểm danh</button>
                                    </form>
                                @elseif($record?->notes)
                                    <p class="rounded-lg bg-[#fcf7f3] px-3 py-2 text-xs leading-5 text-muted"><strong class="text-ink">Ghi chú:</strong> {{ $record->notes }}</p>
                                @else
                                    <p class="text-xs text-muted">{{ $booking->status === 'completed' ? 'Đơn đã hoàn tất.' : 'Không thể cập nhật điểm danh ở trạng thái hiện tại.' }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="flex min-h-56 flex-col items-center justify-center rounded-2xl border border-dashed border-[#e5d7cb] bg-[#fffdf9] px-6 py-10 text-center">
                    <span class="grid size-12 place-items-center rounded-2xl bg-[#fcf0e9] text-brand-700"><x-icon name="users" class="size-6" /></span>
                    <h3 class="mt-4 text-sm font-bold text-ink">Chưa có khách được xác nhận</h3>
                    <p class="mt-1 max-w-sm text-xs leading-5 text-muted">Danh sách hành khách sẽ xuất hiện ở đây khi có đơn đặt tour được xác nhận.</p>
                </div>
            @endforelse
        </section>

        @if($departure->status !== 'cancelled' && $departure->operation_status === 'completed')
            <section class="rounded-2xl border border-[#eadfd4] bg-[#fffdf9] p-5 shadow-[0_3px_14px_rgba(56,38,25,.035)] sm:p-7">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#fcf0e9] text-brand-700"><x-icon name="briefcase" class="size-5" /></span>
                    <div><p class="text-[10px] font-extrabold uppercase tracking-[.15em] text-brand-700">Kết thúc hành trình</p><h2 class="mt-1 font-serif text-2xl font-medium text-ink">Báo cáo chuyến đi</h2><p class="mt-1 text-xs leading-5 text-muted">Ghi lại tổng kết, sự cố và đề xuất sau chuyến.</p></div>
                </div>
                <form method="POST" action="{{ route('operations.report', $departure) }}" class="mt-6 grid gap-4 lg:grid-cols-2">
                    @csrf @method('PUT')
                    <label class="grid gap-1.5 text-xs font-semibold text-muted lg:col-span-2">Tổng kết chuyến đi
                        <textarea class="input mt-1 block w-full text-sm" name="summary" rows="5" maxlength="10000" required placeholder="Chia sẻ tình hình chung và những điểm đáng chú ý...">{{ old('summary', $report?->summary) }}</textarea>
                    </label>
                    <label class="grid gap-1.5 text-xs font-semibold text-muted lg:col-span-2">Sự cố và đề xuất
                        <textarea class="input mt-1 block w-full text-sm" name="incidents" rows="4" maxlength="10000" placeholder="Ghi sự cố phát sinh hoặc đề xuất cải thiện nếu có...">{{ old('incidents', $report?->incidents) }}</textarea>
                    </label>
                    @if($report?->submitted_at)
                        <p class="self-center text-[11px] text-muted lg:col-span-1">Báo cáo gần nhất gửi {{ $report->submitted_at->format('d/m/Y H:i') }}</p>
                    @endif
                    <button class="btn-primary justify-self-start lg:col-start-2 lg:justify-self-end"><x-icon name="check" class="size-4" /> {{ $report ? 'Cập nhật báo cáo' : 'Gửi báo cáo' }}</button>
                </form>
            </section>
        @endif
    </div>
@endsection
