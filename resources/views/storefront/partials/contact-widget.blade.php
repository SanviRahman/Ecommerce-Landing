@php
    // The caller is already inside P07's restricted storefront access gate.
    $storefrontContactLinks = app(\App\Support\StorefrontContactLinks::class)->active();
@endphp
@if(count($storefrontContactLinks) > 0)
    <aside class="p10-contact" aria-label="Customer support">
        <details class="p10-contact-menu">
            <summary aria-label="Open customer support options">Contact <span aria-hidden="true">+</span></summary>
            <nav aria-label="Contact options" class="p10-contact-list">
                @foreach($storefrontContactLinks as $contact)
                    <a href="{{ $contact['href'] }}"
                       @if($contact['external']) target="_blank" rel="noopener noreferrer" @endif>
                        {{ $contact['label'] }}
                    </a>
                @endforeach
            </nav>
        </details>
    </aside>
@endif
