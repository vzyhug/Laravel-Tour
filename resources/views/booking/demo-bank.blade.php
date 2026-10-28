@extends('layouts.app')
@section('title', 'Ngân hàng mô phỏng · VietGo')
@section('content')
<section class="container-page py-10 sm:py-14">
    <div class="mx-auto max-w-2xl">
        <p class="eyebrow">Công cụ trình diễn học tập</p><h1 class="section-title mt-2">Ngân hàng mô phỏng.</h1>
        <p class="mt-5 text-sm leading-8 text-muted">Trang này mô phỏng kết quả chuyển khoản để kiểm tra luồng thanh toán QR. Không kết nối ngân hàng và không chuyển tiền thật.</p>
        <div class="card mt-7 p-6 sm:p-8">
            <p class="text-xs text-muted">Mã đặt tour: <strong class="text-ink">{{ $booking->booking_code }}</strong></p><p class="mt-4 break-words font-serif text-4xl text-brand-600">{{ number_format($intent->amount, 0, ',', '.') }}₫</p><p class="mt-3 break-all font-mono text-xs">{{ $intent->reference }}</p>
            @if($state['status'] === 'pending')
                <p class="mt-6 text-sm leading-7">Chọn kết quả để gửi về hệ thống. Trang QR đang mở sẽ tự cập nhật.</p>
                <form action="{{ route('payments.qr.simulate', [$booking, $intent]) }}" method="POST" class="mt-5 grid gap-3">@csrf
                    <button name="outcome" value="success" class="btn-primary w-full" data-demo-success>Mô phỏng thanh toán thành công</button>
                    <button name="outcome" value="failed" class="btn-secondary w-full" data-demo-failed>Mô phỏng giao dịch thất bại</button>
                    <button name="outcome" value="expired" class="btn-secondary w-full" data-demo-expired>Mô phỏng mã hết hạn</button>
                </form>
            @else
                <p class="mt-6 rounded-lg bg-linen p-4 text-sm font-semibold" role="status">{{ ['paid' => 'Đã thanh toán demo thành công.', 'failed' => 'Giao dịch demo thất bại.', 'expired' => 'Mã QR đã hết hạn.', 'cancelled' => 'Mã QR đã đóng.', 'superseded' => 'Đơn đã có giao dịch thanh toán khác.'][$state['status']] }}</p>
                @if($intent->provider_transaction_id)<p class="mt-4 break-all text-xs text-muted">Mã giao dịch: {{ $intent->provider_transaction_id }}</p>@endif
                @if($state['invoice_url'])<a href="{{ $state['invoice_url'] }}" class="btn-primary mt-5">Xem hóa đơn</a>@endif
            @endif
            <a href="{{ route('payments.qr.show', [$booking, $intent]) }}" class="text-link mt-6 block">← Quay lại trang QR</a>
        </div>
    </div>
</section>
@endsection
