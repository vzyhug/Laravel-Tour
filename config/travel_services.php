<?php

return [
    // Dedicated demo-only callback credential; never use this adapter for real payments.
    'demo_callback_secret' => env('TRAVEL_SERVICES_DEMO_CALLBACK_SECRET', env('PLATFORM_DEMO_CALLBACK_SECRET')) ?: hash('sha256', 'vietgo-demo|'.env('APP_KEY', '')),
];
