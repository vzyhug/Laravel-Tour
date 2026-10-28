@extends('layouts.app')
@section('title', 'Thanh toán · '.$booking->booking_code)
@section('content')
<section class="container-page py-10 sm:py-14">
    <p class="eyebrow">Bước cuối / Hoàn thiện hành trình</p>
    <h1 class="section-title mt-2">Sẵn sàng cho những trải nghiệm mới.</h1>
    <p class="mt-4 text-sm text-muted">Kiểm tra đặt tour và chọn phương thức thanh toán của bạn.</p>
    <div class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_350px]">
        <div class="card p-6 sm:p-8">
            <div class="flex flex-wrap justify-between gap-4 border-b border-[#e5d9cf] pb-6"><div><p class="eyebrow text-[9px] text-muted">Hành trình đã giữ chỗ</p><h2 class="mt-2 font-serif text-2xl">{{ $booking->departure->tour->title }}</h2></div><span class="badge h-fit">Mã đặt tour: {{ $booking->booking_code }}</span></div>
            <dl class="mt-6 grid gap-5 text-xs sm:grid-cols-3"><div><dt class="label">Khởi hành</dt><dd class="font-semibold">{{ $booking->departure->departure_date->format('d/m/Y') }}</dd></div><div><dt class="label">Người đồng hành</dt><dd class="font-semibold">{{ $booking->total_adults + $booking->total_children }} hành khách</dd></div><div><dt class="label">Thời gian</dt><dd class="font-semibold">{{ $booking->departure->tour->duration_days }} ngày · {{ $booking->departure->tour->duration_nights }} đêm</dd></div></dl>
            @if($booking->consultation_id) @include('itineraries.accepted-quote', ['booking' => $booking]) @endif
            @if((float) $booking->discount_amount > 0)
                <dl class="mt-6 grid gap-3 rounded-xl bg-sand p-5 text-xs"><div class="flex justify-between gap-3"><dt class="text-muted">Tạm tính</dt><dd>{{ number_format($booking->total_amount + $booking->discount_amount, 0, ',', '.') }}₫</dd></div><div class="flex justify-between gap-3 text-sage"><dt>Giảm giá @if($booking->coupon) ({{ $booking->coupon->code }}) @endif</dt><dd>−{{ number_format($booking->discount_amount, 0, ',', '.') }}₫</dd></div><div class="flex justify-between gap-3 border-t border-[#e5d9cf] pt-3 font-semibold"><dt>Thành tiền</dt><dd>{{ number_format($booking->total_amount, 0, ',', '.') }}₫</dd></div></dl>
            @endif

            @if($hasPaymentReview)
                <div class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-5"><h3 class="font-serif text-xl text-amber-900">Giao dịch cần được kiểm tra</h3><p class="mt-2 text-xs leading-6 text-amber-800">Nhà cung cấp đã gửi thông tin thanh toán chưa khớp với đơn. Tạm thời không thể tạo giao dịch mới; VietGo cần đối soát trước.</p></div>
            @elseif($pendingSePay && $pendingSePay->status === 'pending' && !$booking->payment)
                <div class="mt-8 rounded-xl bg-sand p-5"><h3 class="font-serif text-xl">Bạn đang có phiên thanh toán SePay Sandbox</h3><p class="mt-2 text-xs leading-6 text-muted">Tiếp tục phiên QR hiện tại để tránh tạo giao dịch trùng.</p><a class="btn-primary mt-4" href="{{ route('payments.sepay.show', [$booking, $pendingSePay]) }}">Tiếp tục thanh toán SePay</a></div>
            @elseif($pendingQr && !$booking->payment)
                <div class="mt-8 rounded-xl bg-sand p-5"><h3 class="font-serif text-xl">Bạn đang có mã VietQR còn hiệu lực</h3><p class="mt-2 text-xs leading-6 text-muted">Mở lại mã hiện tại để hoàn tất thanh toán.</p><a class="btn-primary mt-4" href="{{ route('payments.qr.show', [$booking, $pendingQr]) }}">Tiếp tục thanh toán QR</a></div>
            @elseif(!$booking->payment || $booking->payment->status === 'failed')
                <form method="POST" action="{{ route('payments.store', $booking) }}" class="mt-9" id="payment-form">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $attemptKey }}">
                    @include('booking.payment-methods')
                    <label class="label mt-5" for="demo-outcome">Kịch bản mô phỏng cho thanh toán nội bộ</label>
                    <select class="input" name="demo_outcome" id="demo-outcome"><option value="succeeded">Thành công</option><option value="failed">Thất bại để thử lại</option><option value="pending">Chờ xử lý</option></select>
                    <p class="mt-2 text-[10px] leading-5 text-muted">Kịch bản này chỉ áp dụng với thanh toán mô phỏng nội bộ; QR demo và SePay Sandbox nhận kết quả qua luồng riêng.</p>
                    <button class="btn-primary mt-7 w-full lg:hidden">Xác nhận thanh toán <x-icon name="arrow" class="size-4"/></button>
                </form>
            @elseif($booking->payment->status === 'pending')
                <div class="mt-8 rounded-xl bg-sand p-5"><h3 class="font-serif text-xl">Giao dịch mô phỏng đang chờ xử lý.</h3><p class="mt-2 text-sm">Chọn kết quả để kiểm tra luồng demo. Không có tiền thật được chuyển.</p>
                    @foreach($booking->payment->attempts->where('status', 'pending') as $attempt)
                        <form class="mt-4 flex flex-wrap gap-3" method="POST" action="{{ route('payments.demo.resolve', $attempt) }}">@csrf<button class="btn-primary" name="outcome" value="succeeded">Mô phỏng thành công</button><button class="btn-secondary" name="outcome" value="failed">Mô phỏng thất bại</button></form>
                    @endforeach
                </div>
            @else
                <div class="mt-8 flex items-start gap-3 rounded-xl bg-[#d1e8da]/60 p-5 text-sage"><x-icon name="check" class="size-6 shrink-0"/><div><h3 class="font-serif text-xl">Thanh toán đã hoàn tất.</h3><p class="mt-2 text-xs leading-6">Hành trình của bạn đã được xác nhận. Xem lại thông tin và hóa đơn trong tài khoản.</p><a class="text-link mt-3" href="{{ route('customer.bookings.show', $booking) }}">Xem chuyến đi <x-icon name="arrow" class="size-4"/></a></div></div>
            @endif
        </div>
        <aside class="card p-6 lg:sticky lg:top-28"><p class="eyebrow">Tóm tắt thanh toán</p><h2 class="mt-2 font-serif text-2xl">Hành trình của bạn.</h2><dl class="mt-6 grid gap-4 text-xs"><div class="flex justify-between gap-3"><dt class="text-muted">Mã đặt tour</dt><dd class="font-semibold">{{ $booking->booking_code }}</dd></div><div class="flex justify-between gap-3"><dt class="text-muted">Hành khách</dt><dd class="font-semibold">{{ $booking->total_adults + $booking->total_children }} người</dd></div></dl><div class="mt-6 rounded-lg bg-sand p-5"><p class="eyebrow text-[9px] text-muted">Tổng thanh toán</p><p class="mt-2 font-serif text-3xl text-brand-600">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</p></div>@if((!$booking->payment || $booking->payment->status === 'failed') && !$hasPaymentReview && !$pendingQr && !$pendingSePay)<button type="submit" form="payment-form" class="btn-primary mt-5 w-full">Xác nhận thanh toán <x-icon name="arrow" class="size-4"/></button>@endif<p class="mt-4 flex items-start gap-2 text-[10px] leading-6 text-muted"><x-icon name="shield" class="mt-1 size-4 shrink-0 text-sage"/> Lưu giữ mã đặt tour để tra cứu chuyến đi và hóa đơn.</p></aside>
    </div>
</section>
@endsection
