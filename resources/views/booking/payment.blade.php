@extends('layouts.app')
@section('title', 'Thanh toán · '.$booking->booking_code)
@section('content')
<section class="container-page py-10 sm:py-14">
    <p class="eyebrow">Bước cuối / Hoàn thiện hành trình</p>
    <h1 class="section-title mt-2">Sẵn sàng cho những trải nghiệm mới.</h1>
    <p class="mt-4 text-sm text-muted">Kiểm tra đơn tour và chọn phương thức thanh toán.</p>
    <div class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_350px]">
        <div class="card p-6 sm:p-8">
            <div class="flex flex-wrap justify-between gap-4 border-b border-[#e5d9cf] pb-6">
                <div><p class="eyebrow text-[9px] text-muted">Hành trình đã giữ chỗ</p><h2 class="mt-2 font-serif text-2xl">{{ $booking->departure->tour->title }}</h2></div>
                <span class="badge h-fit">Mã đặt tour: {{ $booking->booking_code }}</span>
            </div>
            <dl class="mt-6 grid gap-5 text-xs sm:grid-cols-3">
                <div><dt class="label">Khởi hành</dt><dd class="font-semibold">{{ $booking->departure->departure_date->format('d/m/Y') }}</dd></div>
                <div><dt class="label">Hành khách</dt><dd class="font-semibold">{{ $booking->total_adults + $booking->total_children }} người</dd></div>
                <div><dt class="label">Thời gian</dt><dd class="font-semibold">{{ $booking->departure->tour->duration_days }} ngày · {{ $booking->departure->tour->duration_nights }} đêm</dd></div>
            </dl>
            @if($booking->consultation_id)
                @include('itineraries.accepted-quote', ['booking' => $booking])
            @endif
            @if((float) $booking->discount_amount > 0)
                <dl class="mt-6 grid gap-3 rounded-xl bg-sand p-5 text-xs">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Tạm tính</dt><dd>{{ number_format($booking->total_amount + $booking->discount_amount, 0, ',', '.') }}₫</dd></div>
                    <div class="flex justify-between gap-3 text-sage"><dt>Giảm giá @if($booking->coupon) ({{ $booking->coupon->code }}) @endif</dt><dd>−{{ number_format($booking->discount_amount, 0, ',', '.') }}₫</dd></div>
                    <div class="flex justify-between gap-3 border-t border-[#e5d9cf] pt-3 font-semibold"><dt>Thành tiền</dt><dd>{{ number_format($booking->total_amount, 0, ',', '.') }}₫</dd></div>
                </dl>
            @endif
            @if($pendingMomo)
                <div class="mt-8 rounded-xl bg-sand p-5">
                    <h3 class="font-serif text-xl">Bạn đang có phiên MoMo QR</h3>
                    <p class="mt-2 text-xs leading-6 text-muted">Mở lại mã QR hiện tại để tiếp tục thanh toán đúng số tiền của đơn.</p>
                    <a class="btn-primary mt-4" href="{{ route('payments.momo.show', [$booking, $pendingMomo]) }}">Mở mã MoMo QR</a>
                </div>
            @elseif($pendingVnpay)
                <div class="mt-8 rounded-xl bg-sand p-5">
                    <h3 class="font-serif text-xl">Bạn đang có phiên VNPay QR</h3>
                    <p class="mt-2 text-xs leading-6 text-muted">Tiếp tục phiên thanh toán hiện tại để tránh tạo giao dịch trùng.</p>
                    <a class="btn-primary mt-4" href="{{ route('payments.vnpay.resume', [$booking, $pendingVnpay]) }}">Tiếp tục thanh toán ngân hàng</a>
                    <form method="POST" action="{{ route('payments.vnpay.restart', [$booking, $pendingVnpay]) }}" class="mt-3" onsubmit="return confirm('Chỉ tiếp tục nếu bạn chưa thanh toán và đã đóng trang VNPay cũ. QR cũ sẽ bị đóng và tạo yêu cầu VNPay mới.');">
                        @csrf
                        <button class="btn-secondary w-full">Đóng QR cũ, chọn cách thanh toán khác</button>
                    </form>
                    <p class="mt-2 text-[10px] leading-5 text-muted">Dùng nút này khi phiên cũ chỉ hiện QR và bạn chưa thực hiện thanh toán.</p>
                </div>
            @elseif(!$booking->payment || $booking->payment->status === 'failed')
                <form method="POST" action="{{ route('payments.store', $booking) }}" class="mt-9" id="payment-form">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $attemptKey }}">
                    <fieldset>
                        <legend class="font-serif text-2xl">Phương thức thanh toán</legend>
                        <p class="mt-2 text-xs leading-6 text-muted">Chuyển khoản ngân hàng sẽ mở VNPay QR Sandbox khi đã cấu hình; MoMo cũng tạo mã QR gắn đúng số tiền đơn hàng.</p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach([
                                'bank_transfer' => [$vnpayReady ? 'Chuyển khoản ngân hàng · VNPay NCB Sandbox' : 'Chuyển khoản ngân hàng · VNPay chưa cấu hình', $vnpayReady ? 'Mở trực tiếp màn hình NCB để nhập thông tin thẻ test; không quét QR' : 'Phương thức này tạm khóa đến khi cấu hình VNPay Sandbox trong .env'],
                                'momo' => ['MoMo QR Sandbox', $momoReady ? 'Quét bằng MoMo Test; số tiền và nội dung đã gắn với đơn' : 'Cần cấu hình thông tin MoMo Sandbox trong file .env'],
                                'card' => ['Thẻ tín dụng / ghi nợ', 'Mô phỏng thanh toán bằng thẻ'],
                                'cash' => ['Thanh toán tại văn phòng', 'Xác nhận với nhân viên VietGo'],
                            ] as $value => [$label, $description])
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-[#e5d9cf] p-4 transition has-checked:border-brand-500 has-checked:bg-brand-50 {{ ($value === 'momo' && !$momoReady) || ($value === 'bank_transfer' && !$vnpayReady) ? 'cursor-not-allowed opacity-60' : '' }}">
                                    <input type="radio" name="payment_method" value="{{ $value }}" class="mt-1 size-4 shrink-0"
                                        @checked(old('payment_method') === $value)
                                        @disabled(($value === 'momo' && !$momoReady) || ($value === 'bank_transfer' && !$vnpayReady)) required>
                                    <span><strong class="block text-xs">{{ $label }}</strong><span class="mt-1 block text-[10px] leading-5 text-muted">{{ $description }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="mt-5" id="mock-outcome" data-gateway-methods="{{ implode(',', array_filter([$vnpayReady ? 'bank_transfer' : null, $momoReady ? 'momo' : null])) }}">
                        <label class="label" for="demo-outcome">Kịch bản mô phỏng nội bộ</label>
                        <select class="input" name="demo_outcome" id="demo-outcome" required>
                            <option value="" disabled @selected(!old('demo_outcome'))>Chọn kết quả giao dịch</option>
                            <option value="succeeded" @selected(old('demo_outcome') === 'succeeded')>Thành công</option>
                            <option value="failed" @selected(old('demo_outcome') === 'failed')>Thất bại để thử thanh toán lại</option>
                            <option value="pending" @selected(old('demo_outcome') === 'pending')>Chờ xử lý</option>
                        </select>
                        <p class="mt-1 text-[10px] leading-5 text-muted">Phương thức chưa cấu hình cổng thật sẽ dùng mô phỏng; hãy chọn kết quả cần kiểm tra.</p>
                    </div>
                    @error('payment_method')<p class="mt-4 text-sm text-red-700">{{ $message }}</p>@enderror
                    <button class="btn-primary mt-7 w-full lg:hidden">Tiếp tục thanh toán <x-icon name="arrow" class="size-4"/></button>
                </form>
            @elseif($booking->payment->status === 'pending')
                <div class="mt-8 rounded-xl bg-sand p-5">
                    <h3 class="font-serif text-xl">Giao dịch đang chờ xử lý</h3>
                    <p class="mt-2 text-sm">Chọn kết quả để kiểm tra luồng mô phỏng nội bộ.</p>
                    @foreach($booking->payment->attempts->where('status', 'pending')->where('provider', 'mock') as $attempt)
                        <form class="mt-4 flex flex-wrap gap-3" method="POST" action="{{ route('payments.demo.resolve', $attempt) }}">
                            @csrf
                            <button class="btn-primary" name="outcome" value="succeeded">Mô phỏng thành công</button>
                            <button class="btn-secondary" name="outcome" value="failed">Mô phỏng thất bại</button>
                        </form>
                    @endforeach
                </div>
            @else
                <div class="mt-8 flex items-start gap-3 rounded-xl bg-[#d1e8da]/60 p-5 text-sage">
                    <x-icon name="check" class="size-6 shrink-0"/>
                    <div><h3 class="font-serif text-xl">Thanh toán đã hoàn tất.</h3><p class="mt-2 text-xs leading-6">Hành trình đã được xác nhận. Xem lại thông tin và hóa đơn trong tài khoản.</p><a class="text-link mt-3" href="{{ route('customer.bookings.show', $booking) }}">Xem chuyến đi <x-icon name="arrow" class="size-4"/></a></div>
                </div>
            @endif
        </div>
        <aside class="card p-6 lg:sticky lg:top-28">
            <p class="eyebrow">Tóm tắt thanh toán</p>
            <h2 class="mt-2 font-serif text-2xl">Hành trình của bạn.</h2>
            <dl class="mt-6 grid gap-4 text-xs">
                <div class="flex justify-between gap-3"><dt class="text-muted">Mã đặt tour</dt><dd class="font-semibold">{{ $booking->booking_code }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">Hành khách</dt><dd class="font-semibold">{{ $booking->total_adults + $booking->total_children }} người</dd></div>
            </dl>
            <div class="mt-6 rounded-lg bg-sand p-5"><p class="eyebrow text-[9px] text-muted">Tổng thanh toán</p><p class="mt-2 font-serif text-3xl text-brand-600">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</p></div>
            @if(!$booking->payment || $booking->payment->status === 'failed')
                <button type="submit" form="payment-form" class="btn-primary mt-5 w-full">Tiếp tục <x-icon name="arrow" class="size-4"/></button>
            @endif
            <p class="mt-4 flex items-start gap-2 text-[10px] leading-6 text-muted"><x-icon name="shield" class="mt-1 size-4 shrink-0 text-sage"/> Chỉ nhập thông tin thanh toán trên trang chính thức của cổng thanh toán.</p>
        </aside>
    </div>
</section>
<script>
(() => {
    const form = document.getElementById('payment-form');
    const mockOutcome = document.getElementById('mock-outcome');
    if (!form || !mockOutcome) return;
    const gatewayMethods = mockOutcome.dataset.gatewayMethods.split(',').filter(Boolean);
    const sync = () => {
        const method = form.querySelector('[name="payment_method"]:checked')?.value;
        const usesGateway = gatewayMethods.includes(method);
        mockOutcome.hidden = usesGateway;
        const outcomeSelect = mockOutcome.querySelector('select');
        outcomeSelect.disabled = usesGateway;
        outcomeSelect.required = !usesGateway;
    };
    form.querySelectorAll('[name="payment_method"]').forEach((input) => input.addEventListener('change', sync));
    sync();
})();
</script>
@endsection
