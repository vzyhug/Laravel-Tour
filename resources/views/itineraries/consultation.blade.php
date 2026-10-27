@extends('layouts.app')
@section('title', 'Yêu cầu tư vấn #'.$consultation->id)
@section('content')
<section class="container-page py-10">
    <a class="text-link" href="{{ route('tour-itinerary.index') }}">← Lịch trình của tôi</a>
    <p class="eyebrow mt-6">Yêu cầu #{{ $consultation->id }} · Lịch phiên bản {{ $consultation->itinerary_version }}</p>
    <h1 class="section-title mt-3">{{ $consultation->snapshot['tour'] }}</h1>
    <p class="mt-4 text-sm">Dự kiến {{ $consultation->travel_date->format('d/m/Y') }} · {{ $consultation->adults }} người lớn, {{ $consultation->children }} trẻ em</p>
    <div class="card mt-6 p-6">
        <h2 class="font-serif text-2xl">{{ ['submitted' => 'Đang chờ nhân viên tư vấn', 'quoted' => 'Phương án & báo giá từ nhân viên', 'closed' => 'Yêu cầu đã đóng'][$consultation->status] }}</h2>
        @if($consultation->quoted_total !== null)<p class="price-text mt-4 text-3xl">{{ number_format($consultation->quoted_total, 0, ',', '.') }}₫ <span class="text-sm text-muted">/ tổng cả nhóm</span></p><p class="mt-3 text-sm {{ $consultation->valid_until->lt(today()) ? 'text-red-700' : 'text-muted' }}">{{ $consultation->valid_until->lt(today()) ? 'Báo giá đã hết hiệu lực từ' : 'Có hiệu lực đến hết ngày' }} {{ $consultation->valid_until->format('d/m/Y') }}</p>@endif
        @if($consultation->reply)<p class="mt-4 whitespace-pre-line text-sm leading-8">{{ $consultation->reply }}</p>@endif
        <p class="mt-4 text-xs leading-7 text-muted">Đây là trao đổi tư vấn, chưa xác nhận dịch vụ và chưa tạo đơn thanh toán. Nhân viên sẽ thống nhất điều kiện và cách đặt với bạn. Bản lịch đã gửi được giữ nguyên khi bạn tiếp tục chỉnh sửa.</p>
        @if($consultation->message)<p class="mt-4 whitespace-pre-line text-sm leading-7"><strong>Mong muốn đã gửi:</strong> {{ $consultation->message }}</p>@endif
        <p class="mt-3 text-xs">Liên hệ: {{ $consultation->contact_name }} · {{ $consultation->contact_phone }} · {{ $consultation->contact_email }}</p>
    </div>
    @include('itineraries.snapshot')
    <a class="btn-secondary mt-6" href="{{ route('tours.show', $consultation->itinerary->tour_id) }}#itinerary">Tiếp tục chỉnh lịch riêng</a>
</section>
@endsection
