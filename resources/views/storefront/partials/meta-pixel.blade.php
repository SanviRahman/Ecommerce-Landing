{{-- Reuse only the Campaign Meta IDs and versioned pixel bootstrap.
     Storefront browser tracking only; courier webhooks remain independent. --}}
@php
    $p53MetaIds = \App\Models\TrackingPixel::query()
        ->active()->meta()->whereNotNull('pixel_id')
        ->pluck('pixel_id')
        ->map(static fn ($id) => trim((string) $id))
        ->filter(static fn ($id) => preg_match('/\A[0-9]{5,30}\z/D', $id) === 1)
        ->unique()->values();
    $p53MetaSettings = ['meta' => $p53MetaIds->all(), 'tiktok' => [], 'ga' => []];
@endphp
<meta id="sfaPixelConfig" name="sfa-pixel-configuration"
      content="{{ json_encode($p53MetaSettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}">
@if($p53MetaIds->isNotEmpty())
    {{-- A noscript image is body content, not a valid child of <head>. --}}
    @push('p53-meta-noscript')
        <noscript>
            @foreach($p53MetaIds as $p53PixelId)
                <img src="https://www.facebook.com/tr?id={{ rawurlencode($p53PixelId) }}&amp;ev=PageView&amp;noscript=1"
                     height="1" width="1" alt="" style="display:none">
            @endforeach
        </noscript>
    @endpush
@endif
<script src="{{ asset('frontend/js/tracking-pixels.js') }}?v=p53" defer></script>
