@extends('layouts.app')
@section('title', 'Thanh toán QR · VietGo')
@section('content')
@php
    $labels = ['review' => 'Giao dịch cần kiểm tra', 'pending' => 'Đang chờ thanh toán', 'paid' => 'Thanh toán demo thành công', 'failed' => 'Giao dịch demo thất bại', 'expired' => 'Mã QR đã hết hạn', 'cancelled' => 'Mã QR đã đóng', 'superseded' => 'Đơn đã được thanh toán bằng giao dịch khác'];
    $paid = in_array($state['status'], ['paid', 'superseded'], true);
    $pending = $state['status'] === 'pending';
@endphp
<section class="container-page py-10 sm:py-14" data-qr-payment data-status-url="{{ route('payments.qr.status', [$booking, $intent]) }}">
    <script type="application/json" data-qr-state>{!! json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" data-qr-labels>{!! json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <a class="text-link" href="{{ route('customer.bookings.show', $booking) }}">← Chi tiết chuyến đi</a>
    <p class="eyebrow mt-7">Hoàn thiện hành trình</p><h1 class="section-title mt-2">Thanh toán bằng QR.</h1>
    <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-6 text-amber-900"><strong>Chế độ demo học tập.</strong> Ngân hàng và tài khoản bên dưới là dữ liệu mô phỏng. QR không dùng để chuyển tiền thật; hãy dùng “Ngân hàng mô phỏng” để trình diễn kết quả.</p>
    <ol class="mt-6 grid grid-cols-3 gap-3 text-xs"><li class="rounded-lg bg-linen p-3">1. Đã giữ chỗ</li><li data-qr-step="payment" @class(['rounded-lg p-3', 'bg-brand-50 font-semibold text-brand-600' => !$paid, 'bg-linen' => $paid])>2. Thanh toán QR</li><li data-qr-step="confirmed" @class(['rounded-lg p-3', 'bg-brand-50 font-semibold text-brand-600' => $paid, 'bg-linen' => !$paid])>3. Xác nhận & hóa đơn</li></ol>
    <div class="mt-8 grid items-start gap-7 lg:grid-cols-[1fr_1fr]">
        <div class="card p-5 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-serif text-2xl">VietQR · Demo</h2><span class="rounded-full bg-linen px-3 py-2 text-xs font-semibold" data-qr-status role="status" aria-live="polite">{{ $labels[$state['status']] }}</span></div>
            <div data-qr-active @if(!$pending) hidden @endif>
                @if($pending)<img src="{{ route('payments.qr.image', [$booking, $intent]) }}" alt="Mã QR demo cho đặt tour {{ $booking->booking_code }}, số tiền {{ number_format($intent->amount, 0, ',', '.') }} đồng" width="400" height="400" class="mx-auto mt-5 aspect-square w-full max-w-80 rounded-lg bg-white" data-payment-qr>@endif
                <p class="mt-3 text-center text-xs text-muted">Mã còn hiệu lực <strong class="font-mono text-ink" data-qr-countdown>{{ gmdate('i:s', $state['seconds_remaining']) }}</strong></p>
                <a href="{{ route('payments.qr.image', [$booking, $intent, 'download' => 1]) }}" class="text-link mx-auto mt-3 block w-fit text-center">Tải mã QR (SVG)</a>
                <div class="mt-6 rounded-xl bg-sand p-5"><p class="eyebrow">Trình diễn trước giảng viên</p><p class="mt-3 text-xs leading-7 text-muted">Mở ngân hàng mô phỏng, chọn kết quả giao dịch rồi quay lại đây. Trang sẽ tự cập nhật và chỉ tạo hóa đơn sau khi nhận kết quả thành công.</p><a href="{{ route('payments.qr.demo', [$booking, $intent]) }}" target="_blank" rel="noopener" class="btn-primary mt-4 w-full" data-demo-bank-link>Mở ngân hàng mô phỏng ↗</a></div>
            </div>
            <div class="mt-7 rounded-xl bg-linen p-6" data-qr-result @if($pending) hidden @endif>
                <h3 class="font-serif text-2xl" data-qr-result-title>{{ $labels[$state['status']] }}</h3>
                <p class="mt-3 text-xs leading-7 text-muted" data-qr-result-description>{{ $paid ? 'Đã ghi nhận giao dịch demo và xác nhận đơn. Bạn có thể xem lại chuyến đi và hóa đơn.' : 'Không ghi nhận khoản thanh toán thành công từ mã QR này. Bạn có thể xem lại đơn hoặc tạo mã mới nếu đơn vẫn đang chờ thanh toán.' }}</p>
                <div class="mt-5 flex flex-wrap gap-3"><a href="{{ $state['booking_url'] }}" class="btn-secondary">Xem chuyến đi</a><a href="{{ $state['invoice_url'] ?? '#' }}" class="btn-primary" data-qr-invoice @if(!$state['invoice_url']) hidden @endif>Xem hóa đơn</a></div>
                @if(!$booking->payment && in_array($booking->status, ['pending', 'confirmed'], true))
                <form action="{{ route('payments.store', $booking) }}" method="POST" class="mt-4" data-qr-retry @if(!$state['can_retry']) hidden @endif>@csrf<input type="hidden" name="payment_method" value="bank_transfer"><button class="btn-primary w-full">Tạo mã QR mới</button></form>
                @endif
            </div>
            <div class="mt-5 flex flex-wrap items-center gap-3 text-xs"><button type="button" class="text-link" data-qr-refresh>Kiểm tra trạng thái</button><a href="{{ route('payments.qr.show', [$booking, $intent]) }}" class="text-muted underline">Tải lại trang</a></div>
            <p class="mt-3 text-xs leading-6 text-red-700" role="alert" data-qr-connection hidden></p>
        </div>
        <div class="card p-5 sm:p-8">
            <p class="eyebrow">Thông tin thanh toán</p><h2 class="mt-3 font-serif text-2xl">{{ $booking->departure->tour->title }}</h2>
            <dl class="mt-6 grid gap-5 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">Mã đặt tour</dt><dd class="break-all text-right font-semibold">{{ $booking->booking_code }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Ngân hàng</dt><dd class="text-right">Ngân hàng mô phỏng VietGo</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Tài khoản demo</dt><dd class="text-right font-mono">{{ \App\Services\Payments\DemoQrPayment::ACCOUNT }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Người nhận demo</dt><dd class="text-right">VIETGO DEMO</dd></div>
                <div><dt class="text-muted">Nội dung giao dịch</dt><dd class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-linen p-3"><span class="break-all font-mono text-xs" id="qr-reference">{{ $intent->reference }}</span><button type="button" class="text-link text-xs" data-copy-qr-reference>Sao chép</button></dd></div>
            </dl>
            <p class="mt-3 text-xs text-sage" data-copy-qr-status role="status"></p>
            <div class="mt-6 rounded-xl bg-sand p-5"><p class="eyebrow">Số tiền</p><p class="mt-3 break-words font-serif text-4xl text-brand-600">{{ number_format($intent->amount, 0, ',', '.') }}₫</p><p class="mt-3 text-xs leading-6 text-muted">Tổng tiền đã bao gồm giảm giá của đơn, nếu có.</p></div>
            <details class="mt-6 text-xs"><summary class="cursor-pointer font-semibold">Xem dữ liệu QR demo</summary><p class="mt-3 leading-6 text-muted">Payload theo cấu trúc VietQR, dùng BIN {{ \App\Services\Payments\DemoQrPayment::BANK_BIN }} và tài khoản giả. Ứng dụng ngân hàng thật có thể từ chối mã này. Bộ mô phỏng trên web thay cho ngân hàng khi trình diễn.</p><pre class="mt-3 whitespace-pre-wrap break-all rounded-lg bg-linen p-4 leading-6">{{ $intent->qr_payload }}</pre></details>
        </div>
    </div>
</section>
@endsection
