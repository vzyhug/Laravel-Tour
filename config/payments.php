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
];
