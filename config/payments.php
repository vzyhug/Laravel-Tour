<?php

return [
    // All QR payment actions are disabled in production, even if this flag is true.
    'demo_enabled' => env('PAYMENT_DEMO_ENABLED', false),
    'qr_lifetime_minutes' => (int) env('PAYMENT_QR_LIFETIME_MINUTES', 10),
];
