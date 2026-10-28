@php
    $qrDemo = \App\Services\Payments\DemoQrPayment::enabled();
    $methods = ['cash' => ['Thanh toán tại văn phòng', 'Xác nhận với nhân viên VietGo'], 'momo' => ['Ví điện tử MoMo', 'Thanh toán mô phỏng qua ví điện tử'], 'card' => ['Thẻ tín dụng / ghi nợ', 'Thanh toán mô phỏng bằng thẻ']];
    if ($qrDemo) $methods = ['bank_transfer' => ['Chuyển khoản VietQR · Demo', 'Tạo QR theo đơn và chờ kết quả từ ngân hàng mô phỏng']] + $methods;
@endphp
<fieldset>
    <legend class="font-serif text-2xl">Phương thức thanh toán</legend>
    <p class="mt-2 text-xs leading-6 text-muted">Đây là thanh toán mô phỏng của dự án; không cần nhập thông tin thẻ hay chuyển tiền thực.</p>
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @foreach($methods as $value => [$label, $description])
        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-[#e5d9cf] p-4 transition has-checked:border-brand-500 has-checked:bg-brand-50">
            <input type="radio" name="payment_method" value="{{ $value }}" class="mt-1 size-4 shrink-0" @checked(old('payment_method', $qrDemo ? 'bank_transfer' : 'cash') === $value) required>
            <span><strong class="block text-xs">{{ $label }}</strong><span class="mt-1 block text-[10px] leading-5 text-muted">{{ $description }}</span></span>
        </label>
        @endforeach
    </div>
</fieldset>
