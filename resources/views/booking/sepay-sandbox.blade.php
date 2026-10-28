@extends('layouts.app')
@section('title', 'SePay Sandbox · VietGo')
@section('content')
@php
    $labels = ['pending' => 'Chờ SePay xác nhận', 'paid' => 'Thanh toán sandbox thành công', 'expired' => 'Phiên thanh toán hết hạn', 'cancelled' => 'Phiên thanh toán đã đóng', 'failed' => 'Thanh toán không thành công', 'superseded' => 'Đơn đã được thanh toán', 'review' => 'Giao dịch cần kiểm tra'];
    $pending = $state['status'] === 'pending';
@endphp
<section class="container-page py-10 sm:py-14" data-qr-payment data-status-url="{{ route('payments.sepay.status', [$booking, $intent]) }}">
    <script type="application/json" data-qr-state>{!! json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" data-qr-labels>{!! json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <a class="text-link" href="{{ $state['booking_url'] }}">← Chi tiết chuyến đi</a>
    <p class="eyebrow mt-7">Thanh toán thử nghiệm</p><h1 class="section-title mt-2">QR ngân hàng · SePay Sandbox.</h1>
    <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-7 text-amber-900"><strong>Môi trường Sandbox.</strong> Đây là giao dịch thử nghiệm, không cần chuyển tiền thật. Thực hiện thanh toán bằng công cụ giả lập trên trang SePay.</p>
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
                <p class="text-sm leading-7 text-muted">Mở trang SePay trong tab mới để thực hiện thanh toán thử. Giữ trang này mở để xem đơn tự cập nhật khi có kết quả.</p>
                <p class="mt-4 text-xs">Phiên còn hiệu lực <strong class="font-mono" data-qr-countdown>{{ gmdate('i:s', $state['seconds_remaining']) }}</strong></p>
                @if($fields)
                <img src="{{ route('payments.sepay.phone-image', [$booking, $intent]) }}" alt="QR mở trang SePay Sandbox trên điện thoại" width="400" height="400" class="mx-auto mt-5 aspect-square w-full max-w-72 bg-white" data-payment-qr>
                <p class="mt-3 text-center text-xs leading-6 text-muted">Quét bằng Camera hoặc Google Lens trên Android để mở trang thanh toán thử. Đây là QR liên kết, không phải mã chuyển khoản ngân hàng.</p>
                <form action="{{ \App\Services\Payments\SePaySandbox::CHECKOUT_URL }}" method="POST" target="_blank" rel="noopener" class="mt-5">
                    @foreach($fields as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    <button type="submit" class="btn-primary w-full">Mở thanh toán SePay Sandbox ↗</button>
                </form>
                @else
                <p class="mt-4 text-xs text-red-700">Kết nối SePay chưa sẵn sàng. Vui lòng liên hệ người quản trị.</p>
                @endif
                <p class="mt-4 text-xs leading-7 text-muted">Sau khi trở về từ SePay, hãy chờ xác nhận tại đây. Trở về trang không đồng nghĩa với thanh toán thành công.</p>
            </div>
            <div class="mt-6 rounded-xl bg-linen p-5" data-qr-result @if($pending) hidden @endif>
                <h3 class="font-serif text-2xl" data-qr-result-title>{{ $labels[$state['status']] }}</h3>
                <p class="mt-3 text-xs leading-7" data-qr-result-description></p>
                <div class="mt-5 flex flex-wrap gap-3"><a class="btn-secondary" href="{{ $state['booking_url'] }}">Xem chuyến đi</a><a class="btn-primary" href="{{ $state['invoice_url'] ?? '#' }}" data-qr-invoice @if(!$state['invoice_url']) hidden @endif>Xem hóa đơn</a></div>
                <form action="{{ route('payments.store', $booking) }}" method="POST" class="mt-4" data-qr-retry @if(!$state['can_retry']) hidden @endif>@csrf<input type="hidden" name="payment_method" value="sepay"><button class="btn-primary w-full">Tạo phiên thanh toán mới</button></form>
            </div>
            <button type="button" class="text-link mt-5 text-xs" data-qr-refresh>Kiểm tra trạng thái</button>
            <p class="mt-3 text-xs leading-6 text-red-700" role="alert" data-qr-connection hidden></p>
        </div>
    </div>
</section>
@endsection
