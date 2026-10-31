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
        ->filter(fn ($pixel) => ! empty($pixel->script_code))
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
<meta id="sfaPixelConfig" name="sfa-pixel-configuration"
      content="{{ json_encode($sfaPixelConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}">

{{-- Admin-managed GTM snippets are dynamic database content, not application JS. --}}
@foreach($gtmPixels as $pixel)
    @if(! empty($pixel->script_code))
        {!! $pixel->script_code !!}
    @endif
@endforeach

@if($metaPixels->isNotEmpty())
    <noscript>
        @foreach($metaPixels as $pixel)
            <img height="1" width="1" class="sfa-tracking-pixel-image" alt=""
                 src="https://www.facebook.com/tr?id={{ urlencode($pixel->pixel_id) }}&ev=PageView&noscript=1">
        @endforeach
    </noscript>
@endif

@if($gaPixels->isNotEmpty())
    @php $firstGa = $gaPixels->first(); @endphp
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ rawurlencode((string) $firstGa->pixel_id) }}"></script>
@endif

{{-- First-party pixel bootstrap code is a versioned public asset. --}}
<script src="{{ asset('frontend/js/tracking-pixels.js') }}?v=20261024-frontend2" defer></script>
