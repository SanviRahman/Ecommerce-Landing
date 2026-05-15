@php
    use App\Models\TrackingPixel;

    $trackingScripts = TrackingPixel::query()
        ->active()
        ->whereNotNull('script_code')
        ->where('script_code', '!=', '')
        ->orderByRaw("FIELD(platform, 'gtm', 'meta', 'tiktok', 'google_analytics')")
        ->pluck('script_code')
        ->filter()
        ->unique()
        ->values();
@endphp

<script>
    window.dataLayer = window.dataLayer || [];
</script>

@if ($trackingScripts->isNotEmpty())
    @foreach ($trackingScripts as $scriptCode)
        {!! $scriptCode !!}
    @endforeach
@endif