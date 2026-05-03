@props([
    'name',
    'size' => 18,
    'stroke' => 1.6,
])
@php
    $paths = [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'bell'     => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c0 .66.39 1.25 1 1.51H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'home'     => '<path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/>',
        'book'     => '<path d="M4 4h7a4 4 0 0 1 4 4v12"/><path d="M20 4h-3a4 4 0 0 0-4 4v12"/><path d="M4 4v16h7"/><path d="M20 4v16h-7"/>',
        'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13A4 4 0 0 1 16 11"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'clipboard'=> '<rect x="6" y="4" width="12" height="18" rx="2"/><path d="M9 4V2h6v2"/><path d="M9 10h6M9 14h4"/>',
        'chart'    => '<path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-5"/>',
        'layers'   => '<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>',
        'filter'   => '<path d="M3 4h18l-7 8v6l-4 2v-8z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'check'    => '<path d="M5 13l4 4L19 7"/>',
        'x'        => '<path d="M18 6L6 18M6 6l12 12"/>',
        'chev-r'   => '<path d="M9 6l6 6-6 6"/>',
        'chev-l'   => '<path d="M15 6l-6 6 6 6"/>',
        'chev-d'   => '<path d="M6 9l6 6 6-6"/>',
        'chev-u'   => '<path d="M6 15l6-6 6 6"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4z"/>',
        'eye'      => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'  => '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 19C5 19 1 12 1 12a18.5 18.5 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9 9 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-3.17 4.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>',
        'flag'     => '<path d="M4 22V4"/><path d="M4 4h13l-2 4 2 4H4"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'play'     => '<path d="M6 4l14 8-14 8z"/>',
        'school'   => '<path d="M3 10l9-5 9 5-9 5z"/><path d="M5 11v6l7 3 7-3v-6"/>',
        'trophy'   => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M17 5h3v3a3 3 0 0 1-3 3M7 5H4v3a3 3 0 0 0 3 3"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'sparkle'  => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
        'trending' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
        'shield'   => '<path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6z"/>',
        'download' => '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M3 21h18"/>',
        'upload'   => '<path d="M12 21V9"/><path d="M7 14l5-5 5 5"/><path d="M3 3h18"/>',
        'trash'    => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'more'     => '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'phone'    => '<path d="M22 16.92V21a1 1 0 0 1-1.11 1A19 19 0 0 1 2 4.11 1 1 0 0 1 3 3h4.09a1 1 0 0 1 1 .75l1 4a1 1 0 0 1-.27 1L7 10.5a16 16 0 0 0 6.5 6.5l1.75-1.82a1 1 0 0 1 1-.27l4 1a1 1 0 0 1 .75 1z"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'moon'     => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        'send'     => '<path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4z"/>',
        'target'   => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'grid'     => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    ];
    $body = $paths[$name] ?? '';
@endphp
<svg xmlns="http://www.w3.org/2000/svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="{{ $stroke }}"
     stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
    {!! $body !!}
</svg>
