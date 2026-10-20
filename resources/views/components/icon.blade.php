@props(['name'=>'box'])
@php
$paths=[
'home'=>'<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"/><path d="M9 21v-8h6v8"/>',
'box'=>'<path d="m3 7 9-4 9 4v10l-9 4-9-4Z"/><path d="m3 7 9 4 9-4M12 11v10M8 5l9 4"/>',
'layers'=>'<path d="m12 3 10 5-10 5L2 8Z"/><path d="m2 12 10 5 10-5M2 16l10 5 10-5"/>',
'truck'=>'<path d="M1 4h13v13H1zM14 9h4l4 4v4h-8"/><circle cx="5" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
'users'=>'<circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 4a4 4 0 0 1 0 8M22 21v-3a6 6 0 0 0-4-5"/>',
'receipt'=>'<path d="M5 3 8 5l4-2 4 2 3-2v18l-3-2-4 2-4-2-3 2Z"/><path d="M9 9h6M9 13h6"/>',
'chart'=>'<path d="M3 3v18h18M8 16v-5M13 16V6M18 16v-8"/>',
'calculator'=>'<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M8 6h8M8 10h1M12 10h1M16 10h.1M8 14h1M12 14h1M16 14h.1M8 18h1M12 18h1M16 18h.1"/>',
'clipboard'=>'<path d="M9 4H5v17h14V4h-4"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M8 11h8M8 15h8"/>',
'logout'=>'<path d="M9 4H3v16h6M9 12h13m-5-5 5 5-5 5"/>',
'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
'x'=>'<path d="m6 6 12 12M18 6 6 18"/>',
'leaf'=>'<path d="M20 3c-8-1-15 3-15 9a6 6 0 0 0 6 6c6 0 10-7 9-15Z"/><path d="M3 21 15 9M8 16v-5M8 16h5"/>',
'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
'plus'=>'<path d="M12 4v16M4 12h16"/>',
'search'=>'<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
'shield'=>'<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6Z"/><path d="m8 12 3 3 5-6"/>',
'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
'check'=>'<circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/>',
'printer'=>'<path d="M6 8V3h12v5M6 17H3V8h18v9h-3"/><path d="M6 14h12v7H6zM17 11h.1"/>',
'alert'=>'<path d="m12 3 10 18H2Z"/><path d="M12 9v5M12 17h.1"/>',
];
@endphp
<svg {{ $attributes->merge(['class'=>'ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name]??$paths['box'] !!}</svg>
