@extends('layouts.app')
@section('title', 'Đang mở SePay · VietGo')
@section('content')
<section class="container-page py-14">
    <div class="card mx-auto max-w-xl p-6 sm:p-9">
        <p class="eyebrow">SePay</p><h1 class="mt-3 font-serif text-3xl">Đang mở trang thanh toán…</h1>
        <p class="mt-4 text-sm leading-7 text-muted">Bạn sẽ được chuyển tới SePay để hoàn tất thanh toán.</p>
        <form action="{{ \App\Services\Payments\SePaySandbox::CHECKOUT_URL }}" method="POST" class="mt-6" data-sepay-checkout-form data-booking-id="{{ $booking->getKey() }}" data-return-url="{{ route('payments.sepay.show', [$booking, $intent]) }}">
            @foreach($fields as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
            <button type="submit" class="btn-primary w-full">Tiếp tục tới SePay</button>
        </form>
        <p class="mt-3 text-xs leading-6 text-muted">Nếu trình duyệt chưa tự chuyển trang, hãy bấm nút trên.</p>
        <a href="{{ route('payments.sepay.show', [$booking, $intent]) }}" class="text-link mt-5 inline-block text-xs">Xem trạng thái thanh toán</a>
    </div>
</section>
@endsection
