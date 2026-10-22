@props(['name', 'class' => 'size-5'])
@php
    $paths = [
        'arrow' => 'M4 12h16m-6-6 6 6-6 6',
        'arrow-up' => 'M7 17 17 7M7 7h10v10',
        'search' => 'm21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'bookmark' => 'M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17l-6-4-6 4Z',
        'calendar' => 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
        'map' => 'm3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Zm6-3v15m6-12v15',
        'pin' => 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M13 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
        'user' => 'M20 21v-2a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v2M16 5a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
        'shield' => 'm12 3 8 3v6c0 5-8 9-8 9S4 17 4 12V6l8-3Zm-4 9 3 3 5-6',
        'leaf' => 'M20 4C8 1 2 8 5 15s15 5 15-11ZM4 21l11-11',
        'check' => 'm5 12 4 4L19 6',
        'star' => 'm12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z',
        'clock' => 'M12 8v4l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
        'chevron' => 'm6 9 6 6 6-6',
        'close' => 'm6 6 12 12M6 18 18 6',
        'plus' => 'M12 5v14M5 12h14',
        'phone' => 'M22 16.9v3a2 2 0 0 1-2.2 2A20 20 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.4 2.8a2 2 0 0 1-.6 1.7L7.6 9.5a16 16 0 0 0 6.9 6.9l1.3-1.3a2 2 0 0 1 1.7-.6l2.8.4a2 2 0 0 1 1.7 2Z',
        'sparkle' => 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z',
    ];
@endphp
<svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['arrow'] }}"/></svg>
