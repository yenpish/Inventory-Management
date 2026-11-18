@props(['name' => 'box'])
@php
    $paths = [
        'box' => 'M21 8l-9 5-9-5m9 5v9M3 8l9-5 9 5v10l-9 4-9-4V8zm4.5-2.5l9 5',
        'dashboard' => 'M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z',
        'categories' => 'M3 5a2 2 0 012-2h5l3 3h6a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V5z',
        'location' => 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1116 0zM15 10a3 3 0 11-6 0 3 3 0 016 0z',
        'balances' => 'M4 6h16M4 12h16M4 18h16M8 3v6m8 0v6M8 15v6',
        'history' => 'M3 11a9 9 0 119 10M3 4v7h7m2-5v6l4 2',
        'search' => 'M21 21l-5-5m2-6a8 8 0 11-16 0 8 8 0 0116 0z',
        'arrow' => 'M5 12h14m-5-5l5 5-5 5',
        'chevron' => 'M6 9l6 6 6-6',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'close' => 'M6 6l12 12M6 18L18 6',
        'logout' => 'M9 4H4v16h5m5-12l4 4-4 4m-5-4h12',
        'shield' => 'M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3zm-4 9l3 3 5-6',
        'clock' => 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="{{ $paths[$name] ?? $paths['box'] }}" />
</svg>
