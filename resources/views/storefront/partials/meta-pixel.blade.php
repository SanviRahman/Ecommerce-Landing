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
    // Match Campaign: existing admin-configured GTM containers must also load
    // on published Storefront and Homepage pages (not in admin previews).
    $p53GtmPixels = \App\Models\TrackingPixel::query()->active()->gtm()
        ->where(function ($query) {
            $query->whereNotNull('pixel_id')->orWhereNotNull('script_code');
        })->get()->unique('pixel_id');
@endphp
{{-- Share the same GTM loader between Campaign and Storefront. --}}
<script>window.dataLayer = window.dataLayer || [];</script>
@foreach($p53GtmPixels as $p53GtmPixel)
    @include('frontend.partials.gtm-container', [
        'container' => $p53GtmPixel, 'noscriptStack' => 'p53-meta-noscript',
    ])
@endforeach
<meta id="gtmPixelConfig" name="gtm-pixel-configuration"
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
<script src="{{ asset('frontend/js/tracking-pixels.js') }}?v=20261104-tracking-v4"></script>
