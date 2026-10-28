@php
    $vnpay = \App\Services\Payments\VnpayPaymentGateway::ready();
    $momo = \App\Services\Payments\MomoPaymentGateway::ready();
    $sepay = \App\Services\Payments\SePaySandbox::ready();
    $qrDemo = \App\Services\Payments\DemoQrPayment::enabled();
    $methods = [
        'bank_transfer' => [$vnpay ? 'Chuyển khoản ngân hàng · VNPay NCB Sandbox' : 'Chuyển khoản ngân hàng · VNPay chưa cấu hình', $vnpay ? 'Mở trang NCB Sandbox để nhập thẻ test.' : 'Phương thức này bị khóa đến khi cấu hình VNPay Sandbox.', ! $vnpay],
        'momo' => [$momo ? 'Ví MoMo · Sandbox' : 'Ví MoMo · chưa cấu hình', $momo ? 'Thanh toán qua luồng MoMo Sandbox.' : 'Phương thức này bị khóa đến khi cấu hình MoMo Sandbox.', ! $momo],
        'cash' => ['Thanh toán tại văn phòng', 'Thanh toán nội bộ khi xác nhận với nhân viên VietGo.', false],
        'card' => ['Thẻ tín dụng / ghi nợ (mô phỏng)', 'Luồng mô phỏng nội bộ.', false],
    ];
    if ($sepay) $methods = ['sepay' => ['QR ngân hàng · SePay Sandbox', 'SePay mở ở tab mới; VietGo tự kiểm tra kết quả.', false]] + $methods;
    if ($qrDemo) $methods['vietqr_demo'] = ['QR VietQR demo', 'Mã QR nội bộ để kiểm thử; không phải giao dịch ngân hàng thật.', false];
    $defaultMethod = $vnpay ? 'bank_transfer' : ($sepay ? 'sepay' : ($momo ? 'momo' : null));
    $gatewayMethods = implode(',', array_values(array_filter([
        $vnpay ? 'bank_transfer' : null,
        $momo ? 'momo' : null,
        $sepay ? 'sepay' : null,
        $qrDemo ? 'vietqr_demo' : null,
    ])));
@endphp
<fieldset>
    <legend class="font-serif text-2xl">Phương thức thanh toán</legend>
    <p class="mt-2 text-xs leading-6 text-muted">Chọn một cổng thanh toán hoặc phương thức nội bộ. Sandbox dùng dữ liệu thử nghiệm, không trừ tiền thật.</p>
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @foreach($methods as $value => [$label, $description, $disabled])
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-[#e5d9cf] p-4 transition has-checked:border-brand-500 has-checked:bg-brand-50 {{ $disabled ? 'cursor-not-allowed opacity-60' : '' }}">
                <input type="radio" name="payment_method" value="{{ $value }}" class="mt-1 size-4 shrink-0"
                    @checked(old('payment_method', $defaultMethod) === $value) @disabled($disabled) required>
                <span><strong class="block text-xs">{{ $label }}</strong><span class="mt-1 block text-[10px] leading-5 text-muted">{{ $description }}</span></span>
            </label>
        @endforeach
    </div>
</fieldset>
<div class="mt-5" id="mock-outcome" data-gateway-methods="{{ $gatewayMethods }}">
    <label class="label" for="demo-outcome">Kịch bản mô phỏng nội bộ</label>
    <select class="input" name="demo_outcome" id="demo-outcome" required>
        <option value="" disabled @selected(! old('demo_outcome'))>Chọn kết quả cho phương thức mô phỏng</option>
        <option value="succeeded" @selected(old('demo_outcome') === 'succeeded')>Thành công</option>
        <option value="failed" @selected(old('demo_outcome') === 'failed')>Thất bại</option>
        <option value="pending" @selected(old('demo_outcome') === 'pending')>Chờ xử lý</option>
    </select>
    <p class="mt-1 text-[10px] leading-5 text-muted">Không áp dụng cho VNPay, MoMo, SePay hoặc QR VietQR demo.</p>
</div>
<script>
(() => {
    const form = document.getElementById('payment-form');
    const outcome = document.getElementById('mock-outcome');
    if (!form || !outcome) return;
    const gatewayMethods = outcome.dataset.gatewayMethods.split(',').filter(Boolean);
    const sync = () => {
        const method = form.querySelector('[name="payment_method"]:checked')?.value;
        const external = gatewayMethods.includes(method);
        outcome.hidden = external;
        const select = outcome.querySelector('select');
        select.disabled = external;
        select.required = !external;
    };
    form.querySelectorAll('[name="payment_method"]').forEach((input) => input.addEventListener('change', sync));
    sync();
})();
</script>