@extends('layouts.app')
@section('title', 'Thanh toán · ' . $booking->booking_code)
@section('content')
    <section class="container-page py-12">
        <div class="mx-auto max-w-3xl">
            <p class="font-semibold text-brand-700">Bước 2 / 2</p>
            <h1 class="mt-1 text-3xl font-black text-ink">Xác nhận thanh toán</h1>
            <div class="card mt-7 p-6">
                <div class="flex flex-wrap justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <p class="text-sm text-slate-500">Mã đặt tour</p>
                        <strong class="text-xl">{{ $booking->booking_code }}</strong>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-slate-500">Tổng thanh toán</p>
                        <strong class="text-2xl text-orange-600">{{ number_format($booking->total_amount, 0, ',', '.') }}₫</strong>
                    </div>
                </div>
                <div class="mt-5">
                    <h2 class="font-bold">{{ $booking->departure->tour->title }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $booking->total_adults }} người lớn · {{ $booking->total_children }} trẻ em</p>
                </div>

                @if ((float) $booking->discount_amount > 0)
                    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between">
                            <span>Tạm tính</span>
                            <span>{{ number_format($booking->total_amount + $booking->discount_amount, 0, ',', '.') }}₫</span>
                        </div>
                        <div class="mt-1 flex justify-between text-emerald-700">
                            <span>Giảm giá @if ($booking->coupon)(mã {{ $booking->coupon->code }})@endif</span>
                            <span>-{{ number_format($booking->discount_amount, 0, ',', '.') }}₫</span>
                        </div>
                        <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 font-bold">
                            <span>Thành tiền</span>
                            <span>{{ number_format($booking->total_amount, 0, ',', '.') }}₫</span>
                        </div>
                    </div>
                @endif

                @if (!$booking->payment)
                    <form method="POST" action="{{ route('payments.store', $booking) }}" class="mt-7 grid gap-3">
                        @csrf
                        <label class="label">Phương thức thanh toán</label>
                        @foreach (['bank_transfer' => 'Chuyển khoản ngân hàng', 'momo' => 'Ví điện tử MoMo', 'card' => 'Thẻ tín dụng / ghi nợ', 'cash' => 'Thanh toán tại văn phòng'] as $value => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 hover:border-brand-500">
                                <input type="radio" name="payment_method" value="{{ $value }}" required>
                                <span class="font-semibold">{{ $label }}</span>
                            </label>
                        @endforeach
                        <button class="btn-primary mt-3">Thanh toán {{ number_format($booking->total_amount, 0, ',', '.') }}₫</button>
                    </form>
                @else
                    <p class="mt-7 rounded-xl bg-emerald-50 p-4 font-semibold text-emerald-700">Đơn hàng đã được thanh toán.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
