@extends('layouts.admin')
@section('title', 'Tư vấn #'.$consultation->id)
@section('content')
<a class="text-link" href="{{ route('admin.itinerary-consultations.index') }}">← Danh sách yêu cầu</a>
<h1 class="mt-5 text-3xl font-black">#{{ $consultation->id }} · {{ $consultation->snapshot['tour'] }}</h1>
<div class="card mt-5 p-6"><p>{{ $consultation->contact_name }} · {{ $consultation->contact_phone }} · {{ $consultation->contact_email }}</p><p class="mt-3">Dự kiến {{ $consultation->travel_date->format('d/m/Y') }} · {{ $consultation->adults + $consultation->children }} khách</p><p class="mt-3 whitespace-pre-line">{{ $consultation->message }}</p><p class="mt-4 text-sm text-muted">Bản gửi phiên bản {{ $consultation->itinerary_version }}, hiện khách đang lưu phiên bản {{ $consultation->itinerary->version }}. Báo giá phải dựa trên bản gửi bên dưới.</p></div>
@include('itineraries.snapshot')
@if(in_array($consultation->status, ['submitted', 'quoted'], true))
<form class="card mt-6 grid gap-4 p-6" method="POST" action="{{ route('admin.itinerary-consultations.update', $consultation) }}">
    @csrf @method('PATCH')<input type="hidden" name="version" value="{{ $consultation->version }}">
    <h2 class="text-xl font-bold">Phản hồi phương án và báo giá</h2>
    <p class="text-sm text-muted">Kiểm tra thực tế giờ mở cửa, quãng đường, số người và dịch vụ trước khi báo giá. Tổng giá cho cả nhóm; ghi rõ dịch vụ, thuế/phụ phí, điều kiện và phần chưa bao gồm. Phản hồi này không tự tạo đơn hoặc thu tiền.</p>
    <label><span class="label">Xử lý</span><select class="field" name="status"><option value="quoted" @selected(old('status', $consultation->status) !== 'closed')>Gửi báo giá</option><option value="closed" @selected(old('status') === 'closed')>Đóng yêu cầu kèm lý do</option></select></label>
    <label><span class="label">Tổng báo giá cả nhóm (VND)</span><input class="field" name="quoted_total" type="number" min="1" max="999999999999" step="1" value="{{ old('quoted_total', $consultation->quoted_total) }}"></label>
    <label><span class="label">Hiệu lực đến hết ngày (không sau ngày đi)</span><input type="date" name="valid_until" class="field" min="{{ today()->toDateString() }}" max="{{ $consultation->travel_date->toDateString() }}" value="{{ old('valid_until', $consultation->valid_until?->toDateString()) }}"></label>
    <label><span class="label">Phương án / Điều kiện / Nội dung phản hồi</span><textarea class="field" name="reply" rows="7" maxlength="5000" required>{{ old('reply', $consultation->reply) }}</textarea></label>
    <button class="btn-primary">Lưu & gửi phản hồi cho khách</button>
</form>
@else<div class="card mt-6 p-6"><h2 class="font-bold">{{ \App\Models\ItineraryConsultation::STATUS_LABELS[$consultation->status] ?? $consultation->status }}</h2><p class="mt-4 whitespace-pre-line">{{ $consultation->reply }}</p>@if($consultation->booking)<a class="text-link mt-4 inline-block" href="{{ route('admin.bookings.show', $consultation->booking) }}">Đơn {{ $consultation->booking->booking_code }}</a>@endif</div>@endif
@endsection
