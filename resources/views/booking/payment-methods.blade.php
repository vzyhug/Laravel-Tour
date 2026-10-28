@php
    $sepay = \App\Services\Payments\SePaySandbox::ready();
    $qrDemo = \App\Services\Payments\DemoQrPayment::enabled();
    $methods = ['bank_transfer' => ['Chuyển khoản ngân hàng', $qrDemo ? 'Tạo mã VietQR theo đơn và nhận kết quả mô phỏng' : 'Thanh toán chuyển khoản mô phỏng'], 'cash' => ['Thanh toán tại văn phòng', 'Xác nhận với nhân viên VietGo'], 'momo' => ['Ví điện tử MoMo', 'Thanh toán mô phỏng qua ví điện tử'], 'card' => ['Thẻ tín dụng / ghi nợ', 'Thanh toán mô phỏng bằng thẻ']];
    if ($sepay) $methods = ['sepay' => ['QR ngân hàng · SePay Sandbox', 'Thử thanh toán tại SePay và tự xác nhận khi nhận IPN']] + $methods;
@endphp
<fieldset>
    <legend class="font-serif text-2xl">Phương thức thanh toán</legend>
    <p class="mt-2 text-xs leading-6 text-muted">Đây là thanh toán mô phỏng của dự án; không cần nhập thông tin thẻ hay chuyển tiền thực.</p>
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @foreach($methods as $value => [$label, $description])
        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-[#e5d9cf] p-4 transition has-checked:border-brand-500 has-checked:bg-brand-50">
            <input type="radio" name="payment_method" value="{{ $value }}" class="mt-1 size-4 shrink-0" @checked(old('payment_method', $sepay ? 'sepay' : 'bank_transfer') === $value) required>
            <span><strong class="block text-xs">{{ $label }}</strong><span class="mt-1 block text-[10px] leading-5 text-muted">{{ $description }}</span></span>
        </label>
        @endforeach
    </div>
</fieldset>
