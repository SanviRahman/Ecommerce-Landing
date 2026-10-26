<svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@if($platform === 'facebook')
    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
@elseif($platform === 'instagram')
    <rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="18" cy="6" r="1" fill="currentColor" stroke="none"/>
@elseif($platform === 'youtube')
    <rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor" stroke="none"/>
@elseif($platform === 'linkedin')
    <rect x="2" y="2" width="20" height="20" rx="3"/><path d="M7 10v8M7 6v.5M12 18v-8m0 3a3 3 0 0 1 6 0v5"/>
@elseif($platform === 'pinterest')
    <circle cx="12" cy="12" r="10"/><path d="m10 18 2-9m-1 5c6 2 8-7 2-7s-8 7-4 8"/>
@elseif($platform === 'tiktok')
    <path d="M14 3v12a4 4 0 1 1-4-4m4-8c1 3 3 5 7 5"/>
@else
    <path d="m4 4 16 16M20 4 4 20"/>
@endif
</svg>
