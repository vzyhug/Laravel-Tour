# VNPay Sandbox

VNPay is wired into the existing **Chuyển khoản ngân hàng** payment option. When the sandbox credentials are configured, the app creates a signed VNPay payment request with a fixed booking amount. VNPay displays its available payment methods so the tester can select QR, a domestic bank card, or another Sandbox method. MoMo remains a separate payment option.

## Local configuration

Add the following to the local `.env` file. Keep the HashSecret private and do not commit it:

```dotenv
VNPAY_ENABLED=true
VNPAY_TMN_CODE=your_sandbox_tmn_code
VNPAY_HASH_SECRET=your_sandbox_hash_secret
VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=
VNPAY_EXPIRE_MINUTES=15
```

Leave `VNPAY_RETURN_URL` empty to use the application's named return route. Set `APP_URL` to the local address opened in the browser, then refresh Laravel's cached configuration:

```sh
php artisan config:clear
```

The database migration creates `vnpay_payment_sessions` and references the existing payment-attempt record. Apply migrations with `php artisan migrate`.

## Sandbox behavior

- A successful signed VNPay return confirms the existing booking payment; a declined/cancelled return leaves it failed so the customer can retry.
- Return parameters are verified with HMAC-SHA512, merchant code, booking amount, and the transaction reference before payment state is changed.
- Local development uses the VNPay sandbox host only. Production mode intentionally disables this sandbox integration.
- This flow uses the browser return URL. A public VNPay IPN endpoint is not implemented; use a reachable HTTPS callback and implement/enable IPN before relying on asynchronous production settlement.
- Use the current sandbox test-card details from VNPay's [official demo](https://sandbox.vnpayment.vn/apis/vnpay-demo/) and follow the [official payment integration guide](https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html).
