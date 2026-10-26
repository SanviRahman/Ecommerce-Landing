{{-- P34: reviewed, fixed SVG paths. No external icon fonts, no inline scripts. --}}
<svg class="p34-icon {{ $class ?? '' }}" viewBox="0 0 24 24" width="24" height="24"
    fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true" focusable="false">
    @switch($name)
        @case('home')
            <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16"/>
            @break
        @case('grid')
            <rect x="3" y="3" width="7" height="7" rx="1.4"/><rect x="14" y="3" width="7" height="7" rx="1.4"/>
            <rect x="3" y="14" width="7" height="7" rx="1.4"/><rect x="14" y="14" width="7" height="7" rx="1.4"/>
            @break
        @case('heart')
            <path d="M20.8 4.8a5.3 5.3 0 0 0-7.5 0L12 6l-1.3-1.2a5.3 5.3 0 1 0-7.5 7.5L12 21l8.8-8.7a5.3 5.3 0 0 0 0-7.5Z"/>
            @break
        @case('heart-fill')
            <path fill="currentColor" stroke="none" d="M12 21 3.2 12.3A5.3 5.3 0 1 1 10.7 4.8L12 6l1.3-1.2a5.3 5.3 0 1 1 7.5 7.5Z"/>
            @break
        @case('cart')
            <circle cx="9" cy="20" r="1.35"/><circle cx="18" cy="20" r="1.35"/><path d="M2 3h2.5l2.6 12h11.3l2.3-8.5H5.3"/>
            @break
        @case('cart-plus')
            <path d="M2 3h2.5l2.6 12h11.3l1.4-5.2M5.3 6.5h9.2"/><circle cx="9" cy="20" r="1.35"/>
            <circle cx="18" cy="20" r="1.35"/><path d="M19 2v7M15.5 5.5h7"/>
            @break
        @case('account')
            <circle cx="12" cy="7.5" r="4"/><path d="M4.2 21v-1.4a7.8 7.8 0 0 1 15.6 0V21"/>
            @break
        @case('search')
            <circle cx="10.7" cy="10.7" r="6.7"/><path d="m16 16 5 5"/>
            @break
        @case('bag')
            <rect x="4.1" y="8.7" width="15.8" height="12.3" rx="2"/><path d="M8.1 10V7a3.9 3.9 0 0 1 7.8 0v3"/>
            @break
        @case('arrow-right')
            <path d="M4 12h16m-7-7 7 7-7 7"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('chevron-left')
            <path d="m15 5-7 7 7 7"/>
            @break
        @case('chevron-right')
            <path d="m9 5 7 7-7 7"/>
            @break
        @case('close')
            <path d="M5 5 19 19M19 5 5 19"/>
            @break
        @case('check')
            <path d="m4 12 5 5L20 6"/>
            @break
        @case('truck')
            <path d="M3 6h12v11H3zM15 10h3l3 3v4h-6z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>
            @break
        @case('shield')
            <path d="m12 2 8 4v6c0 5-3.6 8.4-8 10-4.4-1.6-8-5-8-10V6z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('headphones')
            <path d="M4 13v-1a8 8 0 0 1 16 0v1M4 13h3v7H5a2 2 0 0 1-2-2v-3a2 2 0 0 1 1-2zm16 0h-3v7h2a2 2 0 0 0 2-2v-3a2 2 0 0 0-1-2z"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>
    @endswitch
</svg>
