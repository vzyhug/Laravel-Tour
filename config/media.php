<?php

return [
    // Business data stays in MySQL. Only image files use this backend.
    'driver' => env('MEDIA_DRIVER', 'supabase'),
    'manifest' => database_path('data/media-cloud.json'),
    'journal' => storage_path('app/private/media-migration.json'),
    'hero' => '/images/tours/demo/da-nang-2.jpg',
    'supabase' => [
        'url' => rtrim(env('SUPABASE_URL', ''), '/'),
        'bucket' => env('SUPABASE_STORAGE_BUCKET', 'tour-images'),
        // Server-side only. Never use a VITE_ variable for this value.
        'secret_key' => env('SUPABASE_SECRET_KEY'),
        'ca_bundle' => env('MEDIA_CA_BUNDLE'),
    ],
];
