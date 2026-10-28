<?php

return [
    // All QR payment actions are disabled in production, even if this flag is true.
    'demo_enabled' => env('PAYMENT_DEMO_ENABLED', false),
    'sepay_sandbox' => [
        'enabled' => env('SEPAY_SANDBOX_ENABLED', false),
        'merchant_id' => env('SEPAY_SANDBOX_MERCHANT_ID', ''),
        'secret_key' => env('SEPAY_SANDBOX_SECRET_KEY', ''),
        'ipn_secret' => env('SEPAY_SANDBOX_IPN_SECRET', ''),
        'public_url' => env('SEPAY_SANDBOX_PUBLIC_URL', ''),
    ],
    'qr_lifetime_minutes' => (int) env('PAYMENT_QR_LIFETIME_MINUTES', 10),
    'vnpay' => [
        'enabled' => (bool) env('VNPAY_ENABLED', false),
        'tmn_code' => env('VNPAY_TMN_CODE', ''),
        'hash_secret' => env('VNPAY_HASH_SECRET', ''),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL', ''),
        'expire_minutes' => (int) env('VNPAY_EXPIRE_MINUTES', 15),
    ],
    'momo' => [
        'enabled' => (bool) env('MOMO_ENABLED', false),
        'partner_code' => env('MOMO_PARTNER_CODE', ''),
        'access_key' => env('MOMO_ACCESS_KEY', ''),
        'secret_key' => env('MOMO_SECRET_KEY', ''),
        'endpoint' => 'https://test-payment.momo.vn',
        'ipn_url' => env('MOMO_IPN_URL', ''),
        'qr_lifetime_minutes' => (int) env('MOMO_QR_LIFETIME_MINUTES', 15),
    ],
];
