@extends('layouts.app')
@section('title', 'Yêu cầu tư vấn #'.$consultation->id)
@section('content')
<section class="container-page py-10">
    <a class="text-link" href="{{ route('tour-itinerary.index') }}">← Lịch trình của tôi</a>
    <p class="eyebrow mt-6">Yêu cầu #{{ $consultation->id }} · Lịch phiên bản {{ $consultation->itinerary_version }}</p>
    <h1 class="section-title mt-3">{{ $consultation->snapshot['tour'] }}</h1>
    <p class="mt-4 text-sm">Dự kiến {{ $consultation->travel_date->format('d/m/Y') }} · {{ $consultation->adults + $consultation->children }} khách</p>
    <div class="card mt-6 p-6">
        <h2 class="font-serif text-2xl">{{ \App\Models\ItineraryConsultation::STATUS_LABELS[$consultation->status] ?? $consultation->status }}</h2>
        @if($consultation->quoted_total !== null)<p class="price-text mt-4 text-3xl">{{ number_format($consultation->quoted_total, 0, ',', '.') }}₫ <span class="text-sm text-muted">/ tổng cả nhóm</span></p><p class="mt-3 text-sm {{ $consultation->valid_until->lt(today()) ? 'text-red-700' : 'text-muted' }}">{{ $consultation->valid_until->lt(today()) ? 'Báo giá đã hết hiệu lực từ' : 'Có hiệu lực đến hết ngày' }} {{ $consultation->valid_until->format('d/m/Y') }}</p>@endif
        @if($consultation->reply)<p class="mt-4 whitespace-pre-line text-sm leading-8">{{ $consultation->reply }}</p>@endif
        @if($consultation->booking)
            <p class="mt-4 text-sm">Đã tạo đơn <strong>{{ $consultation->booking->booking_code }}</strong> theo báo giá và bản lịch trình này.</p>
            <a class="btn-primary mt-4" href="{{ route('customer.bookings.show', $consultation->booking) }}">Xem đơn đặt tour</a>
            @if($consultation->booking->status === 'pending')
                <a class="btn-secondary mt-4" href="{{ route('payments.create', $consultation->booking) }}">Tiếp tục thanh toán</a>
            @endif
        @else
            <p class="mt-4 text-xs leading-7 text-muted">Báo giá chưa tạo đơn thanh toán cho đến khi bạn chấp nhận. Bản lịch đã gửi được giữ nguyên khi bạn tiếp tục chỉnh sửa.</p>
        @endif
        @if($consultation->message)<p class="mt-4 whitespace-pre-line text-sm leading-7"><strong>Mong muốn đã gửi:</strong> {{ $consultation->message }}</p>@endif
        <p class="mt-3 text-xs">Liên hệ: {{ $consultation->contact_name }} · {{ $consultation->contact_phone }} · {{ $consultation->contact_email }}</p>
    </div>
    @include('itineraries.snapshot')
    @if($consultation->canBeAccepted())
        <form class="card mt-6 grid gap-5 p-6" method="POST" action="{{ route('itinerary-consultations.accept', $consultation) }}">
            @csrf
            <input type="hidden" name="version" value="{{ $consultation->version }}">
            <h2 class="font-serif text-2xl">Chấp nhận báo giá & đặt tour riêng</h2>
            <p class="text-sm leading-7 text-muted">Tổng tiền cho {{ $consultation->adults + $consultation->children }} người là {{ number_format($consultation->quoted_total, 0, ',', '.') }}₫. Kiểm tra lịch trình và điều kiện ở trên, sau đó nhập đủ hành khách. Đơn sẽ được giữ chỗ tối đa {{ config('booking.hold_minutes', 30) }} phút để thanh toán, trong thời hạn báo giá và trước giờ khởi hành.</p>
            @for($index = 0; $index < $consultation->adults + $consultation->children; $index++)
                <fieldset class="grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
                    <legend class="px-2 text-sm font-semibold">Hành khách {{ $index + 1 }}{{ $index >= $consultation->adults ? ' · Trẻ em' : '' }}</legend>
                    <label><span class="label">Họ và tên</span><input class="field" name="passengers[{{ $index }}][full_name]" value="{{ \App\Support\CustomerValidation::text(old('passengers.'.$index.'.full_name')) }}" maxlength="100" required></label>
                    <label><span class="label">CCCD / Hộ chiếu</span><input class="field" name="passengers[{{ $index }}][id_card]" value="{{ \App\Support\CustomerValidation::text(old('passengers.'.$index.'.id_card')) }}" minlength="8" maxlength="20" pattern="[0-9A-Za-z]{8,20}" required></label>
                    <label><span class="label">Ngày sinh (không bắt buộc)</span><input class="field" type="date" name="passengers[{{ $index }}][date_of_birth]" value="{{ \App\Support\CustomerValidation::text(old('passengers.'.$index.'.date_of_birth')) }}" max="{{ today()->subDay()->toDateString() }}"></label>
                </fieldset>
            @endfor
            <label><span class="label">Cách tham gia</span><select class="field" name="pickup_type"><option value="self" @selected(old('pickup_type', 'self') === 'self')>Tự đến điểm tập trung</option><option value="door" @selected(old('pickup_type') === 'door')>Đón theo địa chỉ đã thống nhất trong báo giá</option></select></label>
            <label><span class="label">Địa chỉ đón (nếu chọn đón tận nơi)</span><input class="field" name="pickup_address" maxlength="255" value="{{ \App\Support\CustomerValidation::text(old('pickup_address')) }}"></label>
            <label><span class="label">Ghi chú đón khách</span><textarea class="field" name="pickup_note" maxlength="255" rows="2">{{ \App\Support\CustomerValidation::text(old('pickup_note')) }}</textarea></label>
            <label class="flex items-start gap-3 text-sm leading-7"><input type="checkbox" name="terms_accepted" value="1" class="mt-2" required @checked(old('terms_accepted'))><span>Tôi đã đọc và đồng ý với lịch trình, tổng giá và điều kiện báo giá này.</span></label>
            <button class="btn-primary">Chấp nhận báo giá & tiếp tục thanh toán</button>
        </form>
    @endif
    @if($consultation->status === 'quoted')
        <form method="POST" action="{{ route('itinerary-consultations.reject', $consultation) }}" class="mt-5">
            @csrf
            <input type="hidden" name="version" value="{{ $consultation->version }}">
            <button class="btn-secondary">Từ chối báo giá này</button>
        </form>
    @endif
    <a class="btn-secondary mt-6" href="{{ route('tours.show', $consultation->itinerary->tour_id) }}#itinerary">Tiếp tục chỉnh lịch riêng</a>
</section>
@endsection
