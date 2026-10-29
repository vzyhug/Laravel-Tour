@php
    $demoReady = \App\Services\Payments\DemoQrPayment::enabled();
    $methods = [
        'sepay' => ['QR ngân hàng · SePay', 'Thanh toán qua mã QR ngân hàng.', !$sepayReady],
        'cash' => ['Thanh toán tại văn phòng', 'Thanh toán trực tiếp tại văn phòng VietGo.', !$demoReady],
        'card' => ['Thẻ tín dụng / ghi nợ', 'Thanh toán bằng thẻ tín dụng hoặc ghi nợ.', !$demoReady],
        'vietqr_demo' => ['VietQR', 'Thanh toán nhanh bằng mã QR.', !$demoReady],
    ];
    $defaultMethod = $sepayReady ? 'sepay' : ($demoReady ? 'cash' : null);
    $selectedMethod = old('payment_method', $defaultMethod);
    if (!is_string($selectedMethod) || !isset($methods[$selectedMethod]) || $methods[$selectedMethod][2]) $selectedMethod = $defaultMethod;
@endphp
<fieldset>
    <legend class="font-serif text-2xl">Phương thức thanh toán</legend>
    <p class="mt-2 text-xs leading-6 text-muted">Chọn phương thức thanh toán phù hợp.</p>
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        @foreach($methods as $value => [$label, $description, $disabled])
            <label class="flex items-start gap-3 rounded-lg border border-[#e5d9cf] p-4 has-checked:border-brand-500 has-checked:bg-brand-50 {{ $disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' }}">
                <input type="radio" name="payment_method" value="{{ $value }}" class="mt-1 size-4 shrink-0" @checked($selectedMethod === $value) @disabled($disabled) required>
                <span><strong class="block text-xs">{{ $label }}</strong><span class="mt-1 block text-[10px] leading-5 text-muted">{{ $description }}</span></span>
            </label>
        @endforeach
    </div>
    @unless($sepayReady)<p class="mt-4 text-sm text-amber-900" role="status">SePay hiện chưa sẵn sàng. Vui lòng chọn phương thức khác hoặc liên hệ nhân viên.</p>@endunless
</fieldset>
<div class="mt-5" id="mock-outcome">
    <label class="label" for="demo-outcome">Trạng thái giao dịch</label>
    <select class="field" name="demo_outcome" id="demo-outcome" required>
        <option value="" disabled @selected(!old('demo_outcome'))>Chọn trạng thái giao dịch</option>
        <option value="succeeded" @selected(old('demo_outcome') === 'succeeded')>Thành công</option>
        <option value="failed" @selected(old('demo_outcome') === 'failed')>Thất bại</option>
        <option value="pending" @selected(old('demo_outcome') === 'pending')>Chờ xử lý</option>
    </select>
</div>
<script>
(() => {
    const form = document.getElementById('payment-form');
    const outcome = document.getElementById('mock-outcome');
    if (!form || !outcome) return;
    const sync = () => {
        const method = form.querySelector('[name="payment_method"]:checked')?.value;
        const mock = ['cash', 'card'].includes(method);
        outcome.hidden = !mock;
        outcome.querySelector('select').disabled = !mock;
        outcome.querySelector('select').required = mock;
    };
    form.querySelectorAll('[name="payment_method"]').forEach(input => input.addEventListener('change', sync));
    sync();
})();
</script>
