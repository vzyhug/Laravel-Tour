# MoMo QR Sandbox

This project integrates MoMo's test gateway using captureWallet. It creates an order for the exact booking total, displays the MoMo QR/deep link, and trusts only a valid signed MoMo IPN to settle payment. The browser redirect never marks a booking paid.

## Configure

This checkout is configured locally with the public test-only sample credentials from MoMo's own [payment example repository](https://github.com/momo-wallet/payment/blob/master/php/CollectionLink/index.php). They are stored only in the ignored local .env. For a fresh clone, copy that sample's test values into .env:

    MOMO_ENABLED=true
    MOMO_PARTNER_CODE=your_test_partner_code
    MOMO_ACCESS_KEY=your_test_access_key
    MOMO_SECRET_KEY=your_test_secret_key
    MOMO_IPN_URL=https://your-public-https-host/payments/momo/ipn
    MOMO_QR_LIFETIME_MINUTES=15

The gateway endpoint is fixed to https://test-payment.momo.vn; this implementation does not send real production payments. Keep credentials out of source control and chat.

For end-to-end IPN delivery, MoMo must reach the IPN endpoint over public HTTPS. The payment page also queries MoMo's signed merchant API every 10 seconds, so local testing can reconcile a test transaction while a localhost IPN is unreachable. To use IPN too, expose the Laragon app through a temporary HTTPS tunnel, set APP_URL to that public HTTPS address, configure MOMO_IPN_URL to its /payments/momo/ipn path, then run php artisan config:clear.

Install the MoMo Test app and use a test wallet account from the MoMo Developer test instructions. Scan the QR on the payment page; the amount and order reference are created by the server. MoMo's test environment returns success, cancellation, and failure results, then sends the signed transaction notification. The page polls the local payment status until that IPN settles the order.

The app's internal mock selector remains for cash, card, and bank-transfer demos. It is hidden for MoMo and cannot settle a MoMo attempt.

## Result handling

- resultCode=0: settle as successful and confirm the booking.
- MoMo pending result codes remain pending.
- Other transaction result codes settle as failed and allow the customer to try again.
- Duplicate matching IPNs are idempotent. Invalid signatures, amounts, order IDs, partner IDs, and contradictory final results are rejected.

References: [MoMo one-time payment API](https://developers.momo.vn/v3/docs/payment/api/wallet/onetime/), [MoMo collection link and QR test flow](https://developers.momo.vn/v3/vi/docs/payment/api/collection-link/), [MoMo IPN notification format](https://developers.momo.vn/v3/docs/payment/api/result-handling/notification/), [MoMo result codes](https://developers.momo.vn/v3/vi/docs/payment/api/result-handling/resultcode/).
