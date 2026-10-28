<svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@if($platform === 'facebook')
    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
@elseif($platform === 'instagram')
    <rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="18" cy="6" r="1" fill="currentColor" stroke="none"/>
@elseif($platform === 'youtube')
    <rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor" stroke="none"/>
@elseif($platform === 'linkedin')
    <rect x="2" y="2" width="20" height="20" rx="3"/><path d="M7 10v8M7 6v.5M12 18v-8m0 3a3 3 0 0 1 6 0v5"/>
@elseif($platform === 'whatsapp')
    <path d="M20.5 11.6a8.5 8.5 0 0 1-12.4 7.5L3 20.5l1.4-4.9A8.5 8.5 0 1 1 20.5 11.6Z"/>
    <path d="M8.2 7.9c.5-.3.8-.1 1 .3l.7 1.5c.2.4.1.6-.2.9l-.5.5c.7 1.4 1.8 2.4 3.2 3l.6-.7c.3-.3.5-.3.9-.1l1.5.7c.4.2.5.5.3 1-.3.7-1 1.1-1.8 1-3.6-.5-6.5-3.4-7-6.5-.1-.7.4-1.4 1.3-1.6Z"/>
@elseif($platform === 'phone')
    <path d="M7 3H4.5A1.5 1.5 0 0 0 3 4.5C3 13.6 10.4 21 19.5 21a1.5 1.5 0 0 0 1.5-1.5V17l-5-2-2 2a15 15 0 0 1-7-7l2-2-2-5Z"/>
@elseif($platform === 'pinterest')
    <circle cx="12" cy="12" r="10"/><path d="m10 18 2-9m-1 5c6 2 8-7 2-7s-8 7-4 8"/>
@elseif($platform === 'tiktok')
    <path d="M14 3v12a4 4 0 1 1-4-4m4-8c1 3 3 5 7 5"/>
@else
    <path d="m4 4 16 16M20 4 4 20"/>
@endif
</svg>
