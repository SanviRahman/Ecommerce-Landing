@php
    // Independently configured, validated contact actions; never hardcode phone/email.
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
                    @php $method = $contact['label']; @endphp
                    <a href="{{ $contact['href'] }}" class="p38-contact-method p38-contact-{{ strtolower($method) }}"
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
@endif
