<?php

return [
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
