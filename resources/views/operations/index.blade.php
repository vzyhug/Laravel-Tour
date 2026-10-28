@extends('layouts.admin')
@section('title', 'Điều hành chuyến đi')
@section('content')
<h1 class="text-3xl font-black">Điều hành chuyến đi</h1>
<p class="mt-2 text-slate-600">Danh sách chuyến được phân công, điểm danh và báo cáo.</p>
<div class="mt-6 grid gap-4">
    @forelse($departures as $departure)
        <a href="{{ route('operations.show', $departure) }}" class="card block p-5 hover:ring-2 hover:ring-brand-500">
            <h2 class="font-bold">{{ $departure->tour->title }}</h2>
            <p>{{ $departure->departure_date->format('d/m/Y') }} → {{ $departure->return_date->format('d/m/Y') }}</p>
            <p>Hướng dẫn viên: {{ $departure->guide?->full_name ?? 'Chưa phân công' }}</p>
            <p>Trạng thái: {{ ['open' => 'Đang nhận khách', 'full' => 'Đã đủ khách', 'cancelled' => 'Đã hủy'][$departure->status] ?? $departure->status }} · {{ ['scheduled' => 'Chưa khởi hành', 'in_progress' => 'Đang thực hiện', 'completed' => 'Đã hoàn thành'][$departure->operation_status] ?? $departure->operation_status }}</p>
        </a>
    @empty
        <p class="card p-5">Chưa có chuyến được phân công.</p>
    @endforelse
</div>
<div class="mt-6">{{ $departures->links() }}</div>
@endsection
