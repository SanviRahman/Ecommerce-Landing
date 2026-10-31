@php
    $sfaTrackingConfig = [
        'debug' => (bool) config('app.debug'),
        'affiliation' => config('app.name') . ' Online Store',
        'pageType' => request()->is('/') ? 'home' : (request()->is('campaign/*') ? 'campaign' : (request()->is('success/*') ? 'success' : 'other')),
        'pageTitle' => trim($__env->yieldContent('title')) ?: config('app.name'),
        'pageUrl' => url()->current(),
    ];
@endphp
<div id="sfaTrackingConfig" hidden data-config="{{ json_encode($sfaTrackingConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"></div>
<script src="{{ asset('frontend/js/datalayer-events.js') }}?v=20261024-frontend2" defer></script>
