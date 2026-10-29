@php($quote = $booking->quote_snapshot ?? [])
<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hóa đơn {{ $booking->bill->bill_number }}</title>@vite(['resources/css/app.css'])</head>
<body class="bg-white p-5 text-slate-900">
<main class="mx-auto max-w-3xl">
    <div class="flex flex-col justify-between gap-4 border-b-2 border-slate-900 pb-6 sm:flex-row sm:gap-6"><div><h1 class="text-3xl font-black">VietGo Travel</h1><p class="mt-1 text-sm text-slate-500">Hóa đơn dịch vụ du lịch</p></div><div class="sm:text-right"><strong>{{ $booking->bill->bill_number }}</strong><p class="text-sm text-slate-500">{{ $booking->bill->bill_date->format('d/m/Y H:i') }}</p></div></div>
    <section class="mt-8 grid gap-6 sm:grid-cols-2"><div><h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Khách hàng</h2><p class="mt-2 font-bold">{{ $booking->customer->full_name }}</p><p>{{ $booking->customer->email }}<br>{{ $booking->customer->phone }}</p></div><div><h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Chuyến đi</h2><p class="mt-2 font-bold">{{ $quote['tour'] ?? $booking->departure->tour->title }}</p><p>Khởi hành {{ isset($quote['travel_date']) ? \Carbon\Carbon::parse($quote['travel_date'])->format('d/m/Y') : $booking->departure->departure_date->format('d/m/Y') }}@if($booking->departure->time_label) lúc {{ $booking->departure->time_label }}@endif<br>@if($booking->pickup_type === 'door')Đón tại: {{ $booking->pickup_address }}@else Tập trung: {{ (array_key_exists('pickup_point', $quote) ? $quote['pickup_point'] : $booking->departure->tour->pickup_point) ?: 'sẽ thông báo' }}@endif<br>Mã đặt tour {{ $booking->booking_code }}</p></div></section>
    <div class="mt-8 overflow-x-auto"><table class="w-full min-w-[420px] text-left print:min-w-0"><thead class="border-y border-slate-300"><tr><th class="py-3">Nội dung</th><th class="py-3 text-right">Số lượng</th><th class="py-3 text-right">Thành tiền</th></tr></thead>
        <tbody>
            @if($booking->consultation_id)<tr><td class="py-4">Tour riêng theo báo giá đã chấp nhận</td><td class="py-4 text-right">1 gói</td><td class="py-4 text-right">{{ number_format($quote['quoted_total'] ?? $booking->total_amount,0,',','.') }}₫</td></tr>
            @else<tr><td class="py-4">Vé tour theo đơn đã đặt</td><td class="py-4 text-right">{{ $booking->total_adults + $booking->total_children }} hành khách</td><td class="py-4 text-right">{{ number_format((float) $booking->total_amount + (float) $booking->discount_amount,0,',','.') }}₫</td></tr> @endif
        @if($booking->discount_amount > 0)<tr><td class="py-4">Mã giảm giá {{ $booking->coupon?->code }}</td><td class="py-4 text-right"></td><td class="py-4 text-right">-{{ number_format($booking->discount_amount,0,',','.') }}₫</td></tr>@endif
        </tbody>
        <tfoot class="border-t-2 border-slate-900"><tr><th class="py-4 text-xl" colspan="2">Tổng cộng</th><th class="py-4 text-right text-xl">{{ number_format($booking->bill->final_amount,0,',','.') }}₫</th></tr></tfoot>
    </table></div>
    @include('itineraries.accepted-quote', ['booking' => $booking])
    <div class="mt-8 flex flex-wrap gap-3 print:hidden"><button class="btn-primary" onclick="window.print()">In hóa đơn</button><a class="btn-secondary" href="{{ route('customer.bookings.show',$booking) }}">Quay lại</a></div>
</main>
</body>
</html>
