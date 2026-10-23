<?php

return [
    // Dedicated demo-only callback credential; never use this adapter for real payments.
    'demo_callback_secret' => env('PLATFORM_DEMO_CALLBACK_SECRET') ?: hash('sha256', 'vietgo-demo|'.env('APP_KEY', '')),
];
