@php
    // Independently configured, validated contact actions; never hardcode phone/email.
    $storefrontContactLinks = app(\App\Support\StorefrontContactLinks::class)->active();
    $order = ['WhatsApp' => 0, 'Call' => 1, 'Email' => 2, 'Messenger' => 3];
    usort($storefrontContactLinks, static fn($a, $b): int => ($order[$a['label']] ?? 99) <=> ($order[$b['label']] ?? 99));
    $waContact = collect($storefrontContactLinks)->firstWhere('label', 'WhatsApp');
    $waDigits = $waContact ? preg_replace('/\D+/', '', (string) parse_url($waContact['href'], PHP_URL_PATH)) : null;
@endphp
@if(count($storefrontContactLinks) > 0)
    <aside class="p10-contact p38-contact-dock" aria-label="Customer support contact shortcuts" data-p38-contact-dock>
        <details class="p10-contact-menu p38-contact-menu">
            <summary aria-label="Show WhatsApp, phone and email contact options">
                @include('storefront.partials.icon', ['name' => 'headphones'])
                <span class="p38-contact-label">Contact</span>
                <span class="p38-contact-plus" aria-hidden="true">+</span>
            </summary>
            <nav aria-label="Contact methods" class="p10-contact-list p38-contact-list">
                @foreach($storefrontContactLinks as $contact)
                    @php $method = $contact['label']; @endphp
                    <a href="{{ $contact['href'] }}" class="p38-contact-method p38-contact-{{ strtolower($method) }}"
                       @if($method === 'WhatsApp') data-p52-whatsapp @endif
                       @if($contact['external']) target="_blank" rel="noopener noreferrer" @endif
                       aria-label="{{ $method }}{{ $contact['external'] ? ', opens in new tab' : '' }}">
                        <span class="p38-contact-method-icon" aria-hidden="true">
                            @switch($method)
                                @case('WhatsApp') @include('storefront.partials.icon', ['name' => 'whatsapp']) @break
                                @case('Call') @include('storefront.partials.icon', ['name' => 'phone']) @break
                                @case('Email') @include('storefront.partials.icon', ['name' => 'mail']) @break
                                @default @include('storefront.partials.icon', ['name' => 'chat'])
                            @endswitch
                        </span>
                        <span>{{ $method }}</span>
                    </a>
                @endforeach
            </nav>
        </details>
    </aside>
    @if($waContact && $waDigits)
        {{-- A safe local fallback when the external WhatsApp service cannot load.
             Progressive enhancement: without JS the original https link still works. --}}
        <dialog class="p52-whatsapp-dialog" data-p52-whatsapp-dialog aria-labelledby="p52-whatsapp-title">
            <div class="p52-whatsapp-dialog__top">
                <span class="p52-whatsapp-dialog__icon">@include('storefront.partials.icon', ['name'=>'whatsapp'])</span>
                <form method="dialog"><button type="submit" aria-label="Close WhatsApp contact">×</button></form>
            </div>
            <h2 id="p52-whatsapp-title">Chat with us on WhatsApp</h2>
            <p>Choose how you want to connect. If WhatsApp is unavailable on this device, copy the number and contact us later.</p>
            <div class="p52-whatsapp-dialog__number">+{{ $waDigits }}</div>
            <div class="p52-whatsapp-dialog__buttons p54-whatsapp-actions">
                <a href="{{ 'whatsapp://send?phone='.$waDigits }}" class="p54-whatsapp-app" data-p54-whatsapp-app>Open WhatsApp App</a>
                <button type="button" data-p52-copy-number="+{{ $waDigits }}">Copy number</button>
            </div>
            <a class="p54-whatsapp-browser" href="{{ 'https://wa.me/'.$waDigits }}" target="_blank" rel="noopener noreferrer" data-p54-whatsapp-browser>Or open WhatsApp in a separate browser tab</a>
            <p class="p54-whatsapp-embed-note">WhatsApp cannot open inside embedded mobile previews. Use the app, an external tab, or copy the number.</p>
            <p class="p52-whatsapp-dialog__feedback" role="status" aria-live="polite" data-p52-whatsapp-status></p>
        </dialog>
    @endif
@endif
