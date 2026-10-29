@extends('layouts.app')
@section('title', 'Thanh toán MoMo · '.$booking->booking_code)
@section('content')
<section class="container-page max-w-5xl py-10 sm:py-14">
    <p class="eyebrow">Thanh toán qua ví MoMo</p>
    <h1 class="section-title mt-2">Quét mã bằng MoMo Test</h1>
    <p class="mt-3 max-w-2xl text-sm leading-7 text-muted">Mở ứng dụng MoMo Test, chọn quét QR. Số tiền và mã đơn đã được gắn trong mã này.</p>
    <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="card p-6 sm:p-8">
            <div class="grid gap-8 sm:grid-cols-[300px_minmax(0,1fr)] sm:items-center">
                <div class="mx-auto flex min-h-72 w-full max-w-[300px] items-center justify-center rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    @if($session->qr_payload)
                        <img class="aspect-square w-full" src="{{ route('payments.momo.qr', [$booking, $session]) }}" alt="Mã QR thanh toán MoMo">
                    @else
                        <p class="p-5 text-center text-sm leading-6 text-amber-800">MoMo chưa trả về mã QR. Phiên đang được giữ để đối soát, vui lòng không tạo giao dịch khác cho đơn này.</p>
                    @endif
                </div>
                <div>
                    <span class="badge">MoMo Sandbox · Không thu tiền thật</span>
                    <h2 class="mt-4 font-serif text-2xl">Đơn {{ $booking->booking_code }}</h2>
                    <p class="mt-3 text-sm leading-7 text-muted">Dùng ứng dụng MoMo Test và tài khoản thử nghiệm MoMo. Kết quả thành công hoặc thất bại sẽ do MoMo Sandbox trả về; VietGo chỉ xác nhận IPN có chữ ký hợp lệ.</p>
                    @if($session->pay_url)
                        <a class="btn-secondary mt-5 inline-flex" href="{{ $session->pay_url }}" target="_blank" rel="noopener">Mở trang thanh toán MoMo</a>
                    @endif
                    <p class="mt-4 text-xs text-muted">Mã giao dịch: <span class="font-semibold text-ink">{{ $session->order_id }}</span></p>
                </div>
            </div>
            <div id="momo-status" class="mt-8 rounded-xl bg-sand p-5 text-sm" aria-live="polite">Đang chờ MoMo xác nhận giao dịch…</div>
        </div>
        <aside class="card h-fit p-6">
            <p class="eyebrow">Tóm tắt thanh toán</p>
            <h2 class="mt-2 font-serif text-2xl">{{ $booking->departure->tour->title }}</h2>
            <dl class="mt-6 grid gap-4 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-muted">Mã đặt tour</dt><dd class="font-semibold">{{ $booking->booking_code }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">Trạng thái</dt><dd class="font-semibold">{{ \App\Support\UiText::status($session->status) }}</dd></div>
            </dl>
            <div class="mt-6 rounded-lg bg-sand p-5"><p class="eyebrow text-[9px] text-muted">Số tiền đã thiết lập</p><p class="mt-2 font-serif text-3xl text-brand-600">{{ number_format($session->amount, 0, ',', '.') }}₫</p></div>
            <a class="text-link mt-5 inline-flex" href="{{ route('payments.create', $booking) }}">Quay lại trang thanh toán</a>
        </aside>
    </div>
</section>
<script>
(() => {
    const target = document.getElementById('momo-status');
    const statusUrl = @json(route('payments.momo.status', [$booking, $session]));
    const bookingUrl = @json(route('customer.bookings.show', $booking));
    let timer;
    const refreshStatus = async () => {
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('status');
            const state = await response.json();
            if (state.status === 'succeeded') {
                target.textContent = 'MoMo đã xác nhận thanh toán thành công. Đang mở thông tin đặt tour…';
                window.location.assign(bookingUrl);
                return;
            }
            if (state.status === 'failed') {
                target.textContent = 'Giao dịch MoMo thất bại' + (state.result_code ? ' (mã ' + state.result_code + ')' : '') + '. Quay lại trang thanh toán để thử lại.';
                return;
            }
            target.textContent = 'Đang chờ MoMo xác nhận. Giữ trang này mở để tự cập nhật trạng thái.';
        } catch (error) {
            target.textContent = 'Chưa lấy được trạng thái từ máy chủ. Trang sẽ tiếp tục thử lại.';
        }
        timer = window.setTimeout(refreshStatus, 10000);
    };
    refreshStatus();
    window.addEventListener('pagehide', () => window.clearTimeout(timer));
})();
</script>
@endsection
