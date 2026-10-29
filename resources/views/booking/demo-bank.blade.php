@extends('layouts.app')
@section('title', 'Xác nhận thanh toán · VietGo')
@section('content')
<section class="container-page py-10 sm:py-14">
    <div class="mx-auto max-w-2xl">
        <p class="eyebrow">Thanh toán</p><h1 class="section-title mt-2">Xác nhận giao dịch.</h1>
        <div class="card mt-7 p-6 sm:p-8">
            <p class="text-xs text-muted">Mã đặt tour: <strong class="text-ink">{{ $booking->booking_code }}</strong></p><p class="mt-4 break-words font-serif text-4xl text-brand-600">{{ number_format($intent->amount, 0, ',', '.') }}₫</p>
            @if($state['status'] === 'pending')
                <form action="{{ route('payments.qr.simulate', [$booking, $intent]) }}" method="POST" class="mt-5 grid gap-3">@csrf
                    <button name="outcome" value="success" class="btn-primary w-full" data-demo-success>Xác nhận thanh toán</button>
                    <button name="outcome" value="failed" class="btn-secondary w-full" data-demo-failed>Giao dịch không thành công</button>
                    <button name="outcome" value="expired" class="btn-secondary w-full" data-demo-expired>Hết hạn thanh toán</button>
                </form>
            @else
                <p class="mt-6 rounded-lg bg-linen p-4 text-sm font-semibold" role="status">{{ ['paid' => 'Thanh toán thành công.', 'failed' => 'Giao dịch không thành công.', 'expired' => 'Mã QR đã hết hạn.', 'cancelled' => 'Mã QR đã đóng.', 'superseded' => 'Đơn đã có giao dịch thanh toán khác.'][$state['status']] }}</p>
                @if($intent->provider_transaction_id && !str_starts_with($intent->provider_transaction_id, 'DEMO-'))<p class="mt-4 break-all text-xs text-muted">Mã giao dịch: {{ $intent->provider_transaction_id }}</p>@endif
                @if($state['invoice_url'])<a href="{{ $state['invoice_url'] }}" class="btn-primary mt-5">Xem hóa đơn</a>@endif
            @endif
            <a href="{{ route('payments.qr.show', [$booking, $intent]) }}" class="text-link mt-6 block">← Quay lại trang QR</a>
        </div>
    </div>
</section>
@endsection
