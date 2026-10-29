@extends('layouts.app')
@section('title', 'Thanh toán qua SePay · VietGo')
@section('content')
@php
    $labels = ['pending' => 'Đang kiểm tra thanh toán', 'paid' => 'Thanh toán thành công', 'expired' => 'Phiên thanh toán hết hạn', 'cancelled' => 'Phiên thanh toán đã đóng', 'failed' => 'Thanh toán không thành công', 'superseded' => 'Đơn đã được thanh toán', 'review' => 'Giao dịch cần kiểm tra'];
    $pending = $state['status'] === 'pending';
@endphp
<section class="container-page py-10 sm:py-14" data-qr-payment data-status-url="{{ route('payments.sepay.status', [$booking, $intent]) }}">
    <script type="application/json" data-qr-state>{!! json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" data-qr-labels>{!! json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <a class="text-link" href="{{ $state['booking_url'] }}">← Chi tiết chuyến đi</a>
    <p class="eyebrow mt-7">Thanh toán trực tuyến</p><h1 class="section-title mt-2">Kết quả thanh toán SePay.</h1>
    <div class="mt-8 grid items-start gap-7 lg:grid-cols-2">
        <div class="card p-5 sm:p-8">
            <h2 class="font-serif text-2xl">{{ $booking->departure->tour->title }}</h2>
            <dl class="mt-6 grid gap-5 text-sm">
                <div class="flex justify-between gap-4"><dt>Mã đặt tour</dt><dd class="break-all text-right font-semibold">{{ $booking->booking_code }}</dd></div>
                <div><dt>Mã phiên thanh toán</dt><dd class="mt-2 flex flex-wrap gap-3 rounded-lg bg-linen p-3"><span class="break-all font-mono text-xs" id="qr-reference">{{ $intent->reference }}</span><button type="button" class="text-link text-xs" data-copy-qr-reference>Sao chép</button></dd></div>
            </dl>
            <p class="mt-3 text-xs text-sage" data-copy-qr-status role="status"></p>
            <div class="mt-6 rounded-xl bg-sand p-5"><p class="eyebrow">Tổng thanh toán</p><p class="mt-3 break-words font-serif text-4xl text-brand-600">{{ number_format($intent->amount, 0, ',', '.') }}₫</p></div>
        </div>
        <div class="card p-5 sm:p-8">
            <p class="rounded-lg bg-linen p-3 text-sm font-semibold" data-qr-status role="status" aria-live="polite">{{ $labels[$state['status']] }}</p>
            <div class="mt-5" data-qr-active @if(!$pending) hidden @endif>
                <p class="text-sm leading-7 text-muted">Website đang kiểm tra trạng thái giao dịch với SePay và sẽ tự cập nhật kết quả. Hoàn tất thao tác tại tab SePay rồi quay lại đây để xem kết quả.</p>
                <p class="mt-4 text-xs">Phiên còn hiệu lực <strong class="font-mono" data-qr-countdown>{{ gmdate('i:s', $state['seconds_remaining']) }}</strong></p>
                <a href="{{ route('payments.sepay.checkout', [$booking, $intent]) }}" target="_blank" rel="noopener" class="btn-primary mt-5 w-full">Mở lại SePay ↗</a>
                <p class="mt-4 text-xs leading-7 text-muted">Đơn chỉ được xác nhận sau khi VietGo kiểm tra kết quả từ SePay. Đóng trang hoặc trở về website chưa đồng nghĩa với đã thanh toán.</p>
            </div>
            <div class="mt-6 rounded-xl bg-linen p-5" data-qr-result @if($pending) hidden @endif>
                <h3 class="font-serif text-2xl" data-qr-result-title>{{ $labels[$state['status']] }}</h3>
                <p class="mt-3 text-xs leading-7" data-qr-result-description></p>
                <div class="mt-5 flex flex-wrap gap-3"><a class="btn-secondary" href="{{ $state['booking_url'] }}">Xem chuyến đi</a><a class="btn-primary" href="{{ $state['invoice_url'] ?? '#' }}" data-qr-invoice @if(!$state['invoice_url']) hidden @endif>Xem hóa đơn</a></div>
                <form action="{{ route('payments.store', $booking) }}" method="POST" class="mt-4" data-qr-retry @if(!$state['can_retry']) hidden @endif>@csrf<input type="hidden" name="payment_method" value="sepay"><button class="btn-primary w-full">Tạo phiên thanh toán mới</button></form>
            </div>
            <button type="button" class="text-link mt-5 text-xs" data-qr-refresh>Kiểm tra trạng thái</button>
            <p class="mt-3 text-xs leading-6 text-red-700" role="alert" data-qr-connection @if($available) hidden @endif>Chưa kết nối được SePay để xác minh kết quả. Hãy thử kiểm tra lại; đơn chưa được xác nhận từ thông báo này.</p>
        </div>
    </div>
</section>
@endsection
