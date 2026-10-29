@php
    // Site Settings remain the only contact source; never hardcode numbers.
    $storefrontContactLinks = app(\App\Support\StorefrontContactLinks::class)->active();
    $order = ['WhatsApp' => 0, 'Call' => 1, 'Email' => 2, 'Messenger' => 3];
    usort($storefrontContactLinks, static fn($a, $b): int => ($order[$a['label']] ?? 99) <=> ($order[$b['label']] ?? 99));
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
                    @php
                        $method = $contact['label'];
                        $waDigits = $method === 'WhatsApp' ? preg_replace('/\D+/', '', (string) parse_url($contact['href'], PHP_URL_PATH)) : '';
                        $isWhatsApp = $method === 'WhatsApp' && preg_match('/\A[1-9]\d{7,14}\z/D', $waDigits) === 1;
                        // External app handoff only. Neither wa.me nor api.whatsapp.com may
                        // be framed inside the responsive emulator's iframe.
                        $href = $isWhatsApp ? 'https://api.whatsapp.com/send?phone='.$waDigits : $contact['href'];
                        $external = $isWhatsApp || $contact['external'];
                    @endphp
                    <a href="{{ $href }}" class="p38-contact-method p38-contact-{{ strtolower($method) }}"
                       @if($isWhatsApp) data-p55-whatsapp-direct data-p55-mobile-url="{{ 'whatsapp://send?phone='.$waDigits }}" @endif
                       @if($external) target="_blank" rel="noopener noreferrer" @endif
                       aria-label="{{ $method }}{{ $external ? ', opens externally' : '' }}">
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
@endif
