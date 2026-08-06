@props(['nombre'])

@php
    // Mismos trazos que mockups-html/assets/app.js
    $trazos = [
        'mesas'    => '<path d="M3 4h18v12H3z"/><path d="M8 20h8"/><path d="M12 16v4"/>',
        'pedidos'  => '<path d="M4 6h16"/><path d="M4 12h11"/><path d="M4 18h7"/>',
        'caja'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'carta'    => '<path d="M5 3h11l3 3v15H5z"/><path d="M9 9h7"/><path d="M9 13h7"/><path d="M9 17h4"/>',
        'stock'    => '<path d="M3 8l9-5 9 5v8l-9 5-9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>',
        'reportes' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M21 20H3"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>',
        'back'     => '<path d="M19 12H5"/><path d="M11 18l-6-6 6-6"/>',
        'cash'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
        'qr'       => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v3"/><path d="M17 20h4"/>',
        'arrow'    => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
        'card'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>',
        'plus'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'phone'    => '<path d="M5 3h4l2 5-2.5 1.5a12 12 0 006 6L16 13l5 2v4a2 2 0 01-2 2A16 16 0 013 5a2 2 0 012-2z"/>',
        'check'    => '<path d="M5 13l4 4L19 7"/>',
        'config'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.6 1.6 0 00-1.8-.3 1.6 1.6 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.6 1.6 0 00-1-1.5 1.6 1.6 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.6 1.6 0 00.3-1.8 1.6 1.6 0 00-1.5-1H3a2 2 0 110-4h.1a1.6 1.6 0 001.5-1 1.6 1.6 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.6 1.6 0 001.8.3H9a1.6 1.6 0 001-1.5V3a2 2 0 114 0v.1a1.6 1.6 0 001 1.5 1.6 1.6 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.6 1.6 0 00-.3 1.8V9a1.6 1.6 0 001.5 1H21a2 2 0 110 4h-.1a1.6 1.6 0 00-1.5 1z"/>',
    ];
@endphp

<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-linecap="round" stroke-linejoin="round">
    {!! $trazos[$nombre] ?? '' !!}
</svg>
