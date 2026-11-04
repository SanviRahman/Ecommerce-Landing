@php
    $sfaTrackingConfig = [
        'debug' => (bool) config('app.debug'),
        // No Test Event Code or CAPI token is ever rendered into public HTML.
        'localTestMode' => app()->environment(['local', 'testing'])
            && \App\Models\TrackingPixel::query()->active()->meta()
                ->where('capi_enabled', true)
                ->whereNotNull('capi_test_event_code')
                ->where('capi_test_event_code', '!=', '')->exists(),
        'affiliation' => config('app.name') . ' Online Store',
        'pageType' => request()->is('/') ? 'home' : (request()->is('campaign/*') ? 'campaign' : (request()->is('success/*') ? 'success' : 'other')),
        'pageTitle' => trim($__env->yieldContent('title')) ?: config('app.name'),
        'pageUrl' => url()->current(),
        'capiUrl' => app(\App\Services\MetaConversionsApiService::class)->isEnabled()
            ? route('tracking.meta.event', [], false) : null,
    ];
@endphp
<div id="gtmTrackingConfig" hidden data-config="{{ json_encode($sfaTrackingConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"></div>
<script src="{{ asset('frontend/js/datalayer-events.js') }}?v=20261104-tracking-v4" defer></script>
