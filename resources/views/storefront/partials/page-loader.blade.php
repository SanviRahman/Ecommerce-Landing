@php
    // Same Site Settings branding as the storefront header; no hardcoded brand or image URL.
    $loaderBranding = app(\App\Support\StorefrontBranding::class);
    $loaderBrandName = $loaderBranding->name();
    $loaderBrandLogo = $loaderBranding->logo();
    $loaderInitial = mb_strtoupper(mb_substr($loaderBrandName, 0, 1));
@endphp
<div class="p51-page-loader" data-p51-loader role="status" aria-live="polite" aria-label="Loading {{ $loaderBrandName }}">
    <div class="p51-page-loader__inner">
        <span class="p51-page-loader__mark" aria-hidden="true">
            @if($loaderBrandLogo)
                <img src="{{ $loaderBrandLogo }}" alt="" width="76" height="76" loading="eager" fetchpriority="high" decoding="async">
            @else
                <span class="p51-page-loader__initial">{{ $loaderInitial }}</span>
            @endif
        </span>
        <span class="p51-page-loader__brand">{{ $loaderBrandName }}</span>
        <span class="p51-page-loader__tagline">Shop smart · Live better</span>
        <span class="p51-page-loader__track" aria-hidden="true"><span></span></span>
        <span class="p51-page-loader__status">Preparing your shopping experience…</span>
    </div>
</div>
