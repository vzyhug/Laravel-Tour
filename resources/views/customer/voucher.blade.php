@extends('layouts.app')
@section('title', 'Chứng từ demo · '.$booking->booking_code)
@section('content')
<section class="container-page py-10"><article class="card mx-auto max-w-3xl p-6 sm:p-10">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6"><div><p class="eyebrow">VietGo · Chứng từ tour demo</p><h1 class="mt-3 text-2xl">{{ $booking->serviceValue('title', $booking->departure->tour->title) }}</h1></div><strong class="badge">{{ $booking->booking_code }}</strong></div>
    <p class="mt-6 rounded-lg bg-brand-50 p-4 text-sm leading-7 text-brand-700">Chỉ dùng để trình diễn đồ án. Chứng từ này không phải vé thật, không được xác nhận bởi nhà cung cấp và không có giá trị sử dụng dịch vụ.</p>
    <dl class="mt-6 grid gap-6 text-sm sm:grid-cols-2">
        <div><dt class="label">Khởi hành</dt><dd>{{ \Carbon\Carbon::parse($booking->serviceValue('departure_date', $booking->departure->departure_date))->format('d/m/Y') }} · {{ $booking->serviceValue('departure_time', $booking->departure->departure_time) }} (UTC+7)</dd></div>
        <div><dt class="label">Kết thúc</dt><dd>{{ \Carbon\Carbon::parse($booking->serviceValue('return_date', $booking->departure->return_date))->format('d/m/Y') }}</dd></div>
        <div><dt class="label">Điểm tập trung / đón</dt><dd>{{ $booking->pickup_type === 'door' ? $booking->pickup_address : $booking->serviceValue('pickup_point', $booking->departure->tour->pickup_point) }}</dd>@if($booking->pickup_type === 'door')<p class="mt-2 text-xs text-muted">Giờ đón tại địa chỉ cần được xác nhận riêng.</p>@endif</div>
        <div><dt class="label">Nhóm khách</dt><dd>{{ $booking->total_adults }} người lớn · {{ $booking->total_children }} trẻ em</dd></div>
    </dl>
    <h2 class="mt-8 text-lg">Người tham gia</h2><ul class="mt-3 grid gap-2 text-sm">@foreach($booking->passengers as $passenger)<li>{{ $passenger->full_name }} · {{ $passenger->passenger_type === 'adult' ? 'Người lớn' : 'Trẻ em' }}</li>@endforeach</ul>
    <div class="mt-8 border-t border-slate-200 pt-6 text-sm leading-7"><h2 class="text-lg">{{ $booking->service_snapshot ? 'Điều kiện đã chốt' : 'Điều kiện tham khảo' }}</h2>@if(!$booking->service_snapshot)<p class="mt-3 text-muted">Đơn được tạo trước khi lưu bản chụp điều kiện. Nội dung dưới đây lấy từ cấu hình hiện tại; cần đối chiếu tài liệu gốc để xác nhận điều kiện lịch sử.</p>@endif<p class="mt-3">Hủy trước ngày đi tối thiểu {{ $booking->policyValue('cancel_before_days') }} ngày. Hủy trong {{ $booking->policyValue('full_refund_hours') }} giờ từ lúc đặt: hoàn mô phỏng 100%; sau đó {{ $booking->policyValue('partial_refund_percent') }}%, nếu còn đủ điều kiện hủy.</p><p class="mt-3">{{ $booking->serviceValue('included_services', $booking->departure->tour->included_services) }}</p><p class="mt-3 text-muted">Không hiển thị số giấy tờ cá nhân trên chứng từ. Chứng từ tour và hóa đơn là hai tài liệu riêng.</p></div>
    <div class="mt-8 flex flex-wrap gap-3 print:hidden"><button type="button" class="btn-primary" onclick="window.print()">In chứng từ demo</button><a class="btn-secondary" href="{{ route('customer.bookings.show', $booking) }}">Quay lại đơn</a></div>
</article></section>
@endsection
