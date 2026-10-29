@extends('layouts.admin')
@section('title','Chi tiết thanh toán')
@section('content')
<div><p class="font-semibold text-brand-700">Tài chính</p><h1 class="text-3xl font-black text-ink">Chi tiết thanh toán</h1></div>
<div class="card mt-8 grid max-w-3xl gap-4 p-6 text-sm">
    <p><span class="text-slate-500">Mã giao dịch:</span> <strong>{{ $payment->transaction_code }}</strong></p>
    <p><span class="text-slate-500">Ngày thanh toán:</span> {{ $payment->payment_date->format('d/m/Y H:i') }}</p>
    <p><span class="text-slate-500">Khách hàng:</span> {{ $payment->booking->customer->full_name }}</p>
    <p><span class="text-slate-500">Tour:</span> {{ $payment->booking->departure->tour->title }}</p>
    <p><span class="text-slate-500">Số người:</span> {{ $payment->booking->total_adults + $payment->booking->total_children }}</p>
    <p><span class="text-slate-500">Phương thức:</span> {{ str_replace('_',' ',$payment->payment_method) }}</p>
    <p><span class="text-slate-500">Số tiền:</span> <strong>{{ number_format($payment->amount,0,',','.') }}₫</strong></p>
    <p><span class="text-slate-500">Trạng thái:</span> <span class="badge">{{ $payment->status }}</span></p>
</div>
<section class="card mt-6 max-w-3xl p-6">
    <h2 class="text-xl font-bold">Lần thử và hoàn tiền</h2>
    @forelse($payment->attempts as $attempt)
        <p class="mt-2 text-sm">Lần thử #{{ $attempt->id }} · {{ $attempt->status }} · {{ number_format($attempt->amount, 0, ',', '.') }}₫</p>
    @empty
        <p class="mt-2 text-sm text-slate-500">Chưa có lần thử nào.</p>
    @endforelse
    @foreach($payment->refunds as $refund)
        <p class="mt-2 text-sm">Hoàn #{{ $refund->id }} · {{ $refund->status }} · {{ number_format($refund->amount, 0, ',', '.') }}₫ · {{ $refund->reason }}</p>
    @endforeach
</section>
<a class="mt-6 inline-flex items-center rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-600 hover:bg-slate-100" href="{{ route('admin.payments.index') }}">Quay lại</a>
@endsection
