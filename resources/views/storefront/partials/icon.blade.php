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
        @case('leaf')
            <path d="M20 4c-11 1-15 5-15 11a5 5 0 0 0 5 5c6 0 10-4 10-16Z"/><path d="M5 19c3-5 6-8 11-11"/>
            @break
        @case('shirt')
            <path d="M7 3 3 6l-2 5 5 2 1-2v10h10V11l1 2 5-2-2-5-4-3-3 2h-4L7 3Z"/>
            @break
        @case('monitor')
            <rect x="3" y="4" width="18" height="13" rx="2"/><path d="M12 17v4M8 21h8"/>
            @break
        @case('sparkles')
            <path d="m12 2 2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2Z"/><path d="m19 18 .6 1.4L21 20l-1.4.6L19 22l-.6-1.4L17 20l1.4-.6L19 18Z"/>
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
        @case('whatsapp')
            <path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.7-1.2A9 9 0 1 0 12 3Z"/>
            <path d="M8.4 8.2c.5-.5.8-.4 1.1.2l.8 1.5c.2.4 0 .8-.4 1.2l-.3.3c.8 1.5 2 2.5 3.6 3.1l.6-.7c.3-.4.7-.4 1.1-.2l1.3.7c.6.3.7.8.2 1.4-1 1.2-2.8 1.2-5.2-.1-1.8-.9-3.3-2.4-4.2-4.1-.9-1.8-.3-2.8 1.4-3.3z"/>
            @break
        @case('chat')
            <path d="M20.5 11.5a8.5 8.5 0 0 1-8.5 8.5 8.4 8.4 0 0 1-4.1-1.1L3 20l1.4-4.3A8.5 8.5 0 1 1 20.5 11.5z"/>
            <path d="M8.6 8.6c.8 3.1 3.1 5.4 6.2 6.2l1.3-1.2-2.2-1.4-1.1 1.1a5.2 5.2 0 0 1-2.1-2.1l1.1-1.1-1.4-2.2z"/>
            @break
        @case('phone')
            <path d="M22 16.9v3a2 2 0 0 1-2.2 2A19 19 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.6 1.8L7.1 10a16 16 0 0 0 6.9 6.9l1.5-1.9a2 2 0 0 1 1.8-.6l3 .5a2 2 0 0 1 1.7 2z"/>
            @break
        @case('mail')
            <rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3 6 9 7 9-7"/>
            @break
        @case('pin')
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>
            @break
        @case('headphones')
            <path d="M4 13v-1a8 8 0 0 1 16 0v1M4 13h3v7H5a2 2 0 0 1-2-2v-3a2 2 0 0 1 1-2zm16 0h-3v7h2a2 2 0 0 0 2-2v-3a2 2 0 0 0-1-2z"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>
    @endswitch
</svg>
