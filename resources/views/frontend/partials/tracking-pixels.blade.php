@php
    use App\Models\TrackingPixel;

    $activePixels = TrackingPixel::query()
        ->active()
        ->where(function ($query) {
            $query->whereNotNull('pixel_id')
                ->where('pixel_id', '!=', '')
                ->orWhere(function ($q) {
                    $q->whereNotNull('script_code')
                        ->where('script_code', '!=', '');
                });
        })
        ->orderByRaw("FIELD(platform, 'gtm', 'meta', 'tiktok', 'google_analytics')")
        ->get();

    $gtmPixels = $activePixels
        ->where('platform', 'gtm')
        ->unique('pixel_id')
        ->values();

    $metaPixels = $activePixels
        ->where('platform', 'meta')
        ->filter(fn ($pixel) => ! empty($pixel->pixel_id))
        ->unique('pixel_id')
        ->values();

    $tiktokPixels = $activePixels
        ->where('platform', 'tiktok')
        ->filter(fn ($pixel) => ! empty($pixel->pixel_id))
        ->unique('pixel_id')
        ->values();

    $gaPixels = $activePixels
        ->where('platform', 'google_analytics')
        ->filter(fn ($pixel) => ! empty($pixel->pixel_id))
        ->unique('pixel_id')
        ->values();

    $metaPixelIds = $metaPixels->pluck('pixel_id')->filter()->unique()->values();
@endphp

@php
    $sfaPixelConfig = [
        'meta' => $metaPixelIds->all(),
        'tiktok' => $tiktokPixels->pluck('pixel_id')->filter()->values()->all(),
        'ga' => $gaPixels->pluck('pixel_id')->filter()->values()->all(),
    ];
@endphp
<meta id="gtmPixelConfig" name="gtm-pixel-configuration"
      content="{{ json_encode($sfaPixelConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}">

{{-- A configured GTM container (ID or legacy script) owns its official lifecycle events. --}}
<script>window.dataLayer = window.dataLayer || [];</script>
@foreach($gtmPixels as $pixel)
    @include('frontend.partials.gtm-container', [
        'container' => $pixel, 'noscriptStack' => 'sfa-meta-noscript',
    ])
@endforeach

{{-- The no-JS fallback belongs inside <body>, not inside <head>. --}}
@if($metaPixels->isNotEmpty())
    @push('sfa-meta-noscript')
        <noscript>
            @foreach($metaPixels as $pixel)
                <img height="1" width="1" class="sfa-tracking-pixel-image" alt=""
                     src="https://www.facebook.com/tr?id={{ rawurlencode((string) $pixel->pixel_id) }}&amp;ev=PageView&amp;noscript=1">
            @endforeach
        </noscript>
    @endpush
@endif

@if($gaPixels->isNotEmpty())
    @php $firstGa = $gaPixels->first(); @endphp
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ rawurlencode((string) $firstGa->pixel_id) }}"></script>
@endif

{{-- First-party pixel bootstrap code is a versioned public asset. --}}
<script src="{{ asset('frontend/js/tracking-pixels.js') }}?v=20261104-tracking-v4"></script>
