@extends('layouts.admin')
@section('title', 'Chi tiết điều hành chuyến')
@section('content')
<a class="font-semibold text-brand-700" href="{{ route('operations.index') }}">← Danh sách chuyến</a>
<h1 class="mt-4 text-3xl font-black">{{ $departure->tour->title }}</h1>
<p class="mt-2">{{ $departure->departure_date->format('d/m/Y') }} → {{ $departure->return_date->format('d/m/Y') }} · {{ $departure->guide?->full_name ?? 'Chưa phân công' }}</p>
<p>Trạng thái: {{ ['open' => 'Đang nhận khách', 'full' => 'Đã đủ khách', 'cancelled' => 'Đã hủy'][$departure->status] ?? $departure->status }} · {{ ['scheduled' => 'Chưa khởi hành', 'in_progress' => 'Đang thực hiện', 'completed' => 'Đã hoàn thành'][$departure->operation_status] ?? $departure->operation_status }}</p>
@if($departure->status !== 'cancelled' && $departure->operation_status !== 'completed')
    <form method="POST" action="{{ route('operations.status', $departure) }}" class="mt-5">
        @csrf @method('PATCH')
        <input type="hidden" name="operation_status" value="{{ $departure->operation_status === 'scheduled' ? 'in_progress' : 'completed' }}">
        <button class="btn-primary">{{ $departure->operation_status === 'scheduled' ? 'Bắt đầu chuyến' : 'Hoàn thành chuyến' }}</button>
    </form>
@endif
<h2 class="mt-8 text-xl font-bold">Danh sách khách và điểm danh</h2>
<p class="text-sm text-slate-600">Chỉ hiển thị đơn đã xác nhận hoặc hoàn thành. Điểm danh bắt đầu từ ngày khởi hành.</p>
<div class="mt-4 grid gap-4">
@forelse($departure->bookings as $booking)
    <section class="card p-5">
        <h3 class="font-bold">{{ $booking->booking_code }} · {{ $booking->customer->full_name }}</h3>
        <p class="text-sm">Liên hệ: {{ $booking->customer->phone }}</p>
        @foreach($booking->passengers as $passenger)
            @php($record = $attendance->get($passenger->getKey()))
            <div class="mt-4 border-t border-slate-100 pt-4">
                <p class="font-semibold">{{ $passenger->full_name }}</p>
                <p class="text-sm">{{ $record ? ($record->status === 'present' ? 'Có mặt' : 'Vắng mặt') : 'Chưa điểm danh' }}</p>
                @if($departure->status !== 'cancelled' && $departure->operation_status !== 'completed' && $booking->status === 'confirmed' && !$departure->departure_date->isFuture())
                <form method="POST" action="{{ route('operations.attendance', [$departure, $passenger]) }}" class="mt-2 flex flex-wrap items-end gap-3">
                    @csrf @method('PUT')
                    <label class="block text-sm">Điểm danh
                        <select name="status" class="input block"><option value="present" @selected($record?->status === 'present')>Có mặt</option><option value="absent" @selected($record?->status === 'absent')>Vắng mặt</option></select>
                    </label>
                    <label class="block text-sm">Ghi chú<input class="input block" name="notes" maxlength="500" value="{{ $record?->notes }}"></label>
                    <button class="btn-primary">Lưu</button>
                </form>
                @endif
            </div>
        @endforeach
    </section>
@empty
    <p class="card p-5">Chưa có khách đã xác nhận.</p>
@endforelse
</div>
@if($departure->status !== 'cancelled' && $departure->operation_status === 'completed')
<section class="card mt-8 p-5">
    <h2 class="text-xl font-bold">Báo cáo chuyến đi</h2>
    <form method="POST" action="{{ route('operations.report', $departure) }}" class="mt-4 grid gap-4">
        @csrf @method('PUT')
        <label>Tổng kết<textarea class="input mt-1 block w-full" name="summary" rows="5" maxlength="10000" required>{{ old('summary', $report?->summary) }}</textarea></label>
        <label>Sự cố và đề xuất<textarea class="input mt-1 block w-full" name="incidents" rows="4" maxlength="10000">{{ old('incidents', $report?->incidents) }}</textarea></label>
        <button class="btn-primary justify-self-start">Gửi báo cáo</button>
    </form>
</section>
@endif
@endsection
