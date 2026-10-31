@php
    /*
    |--------------------------------------------------------------------------
    | Safe Default Variables
    |--------------------------------------------------------------------------
    */
    $campaign = $campaign ?? null;
    $categories = $categories ?? null;
    $brands = $brands ?? null;
    $reviews = $reviews ?? null;
    $campaignGalleryImages = $campaignGalleryImages ?? null;

    /*
    |--------------------------------------------------------------------------
    | Header Shell and Content Visibility
    |--------------------------------------------------------------------------
    | The header shell remains visible on the homepage even when every campaign
    | is inactive. Only the logo, menu links, mobile toggler and order buttons
    | are hidden, leaving a clean blank header area with the same layout height.
    */
    $showFrontendHeader = isset($showFrontendHeader)
        ? (bool) $showFrontendHeader
        : true;

    $showFrontendHeaderContent = isset($showFrontendHeaderContent)
        ? (bool) $showFrontendHeaderContent
        : ! (request()->routeIs('home') && ! $campaign);

    $siteSetting = $siteSetting ?? \App\Models\SiteSetting::query()
        ->where('status', true)
        ->latest()
        ->first();

    $logo = null;

    if ($siteSetting && method_exists($siteSetting, 'getFirstMediaUrl')) {
        $logo = $siteSetting->getFirstMediaUrl('site_logo');
    }

    $logo = $logo ?: ($siteSetting->logo ?? null);
    $websiteName = $siteSetting->website_name ?? config('app.name', 'Laravel');

    $logoUrl = route('home');

    if ($campaign && ! empty($campaign->slug)) {
        $logoUrl = $campaign->public_url;
    }

    $sectionStatus = function (string $field, bool $default = true) use ($campaign): bool {
        if (! $campaign) {
            return $default;
        }

        return (bool) ($campaign->{$field} ?? $default);
    };

    $hasCollectionItems = function ($items, bool $defaultWhenMissing = true): bool {
        if ($items === null) {
            return $defaultWhenMissing;
        }

        try {
            return collect($items)->isNotEmpty();
        } catch (\Throwable $e) {
            return $defaultWhenMissing;
        }
    };

    $headerCategoryActive = $isCategorySectionActive
        ?? ($sectionStatus('category_section_status') && $hasCollectionItems($categories, false));

    $headerBrandActive = $isBrandSectionActive
        ?? ($sectionStatus('category_section_status') && $hasCollectionItems($brands, false));

    $headerProductActive = $isProductSectionActive
        ?? $sectionStatus('product_section_status');

    $headerDifferenceActive = $isComparisonSectionActive
        ?? $sectionStatus('comparison_section_status');

    $headerServiceActive = $isServiceSectionActive
        ?? $sectionStatus('service_section_status');

    $headerReviewActive = $isReviewSectionActive
        ?? ($sectionStatus('review_section_status') && $hasCollectionItems($reviews, false));

    $headerGalleryHasItems = true;

    if ($campaignGalleryImages !== null) {
        $headerGalleryHasItems = $hasCollectionItems($campaignGalleryImages, false);
    } elseif ($campaign && method_exists($campaign, 'getMedia')) {
        try {
            $headerGalleryHasItems = $campaign->getMedia('campaign_product_gallery')->isNotEmpty();
        } catch (\Throwable $e) {
            $headerGalleryHasItems = true;
        }
    }

    $headerGalleryActive = $isGallerySectionActive
        ?? ($sectionStatus('gallery_section_status') && $headerGalleryHasItems);

    $headerHelpActive = $isHelpSectionActive
        ?? $sectionStatus('help_section_status');

    $headerOrderActive = $isOrderSectionActive
        ?? $sectionStatus('order_section_status');

    $headerOrderTrackingActive = $isOrderTrackingSectionActive
        ?? $sectionStatus('order_tracking_section_status');

    $standaloneFrontendPage = request()->routeIs(
        'order.success',
        'order.track',
        'order.track.search'
    );
    // The public / URL is now the shop, not a campaign with section anchors.
    $standaloneCampaignUrl = $campaign?->public_url ?? route('home');
@endphp

@if($showFrontendHeader)
<header class="front-header">
    <div class="container">
        @if($showFrontendHeaderContent)
            <nav class="navbar navbar-expand-lg front-navbar px-0">
                <a class="navbar-brand d-flex align-items-center" href="{{ $logoUrl }}">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $websiteName }}" class="site-logo-img">
                    @endif
                </a>

                @if($headerOrderActive)
                    <div class="mobile-action d-lg-none ml-auto mr-2">
                        <a href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#order-section' : '#order-section' }}"
                           class="btn btn-success btn-sm order-nav-btn">
                            <i class="fas fa-shopping-bag mr-1"></i>
                            অর্ডার করুন
                        </a>
                    </div>
                @endif

                <button class="navbar-toggler front-toggler"
                        type="button"
                        data-campaign-menu-toggle
                        aria-controls="frontMenu"
                        aria-expanded="false"
                        aria-label="Open campaign navigation">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="navbar-collapse front-offcanvas-menu" id="frontMenu" data-campaign-menu>
                    <div class="front-offcanvas-head d-lg-none">
                        <strong id="front-offcanvas-title">Browse this campaign</strong>
                        <button type="button" data-campaign-menu-close aria-label="Close campaign navigation">&times;</button>
                    </div>
                    <ul class="navbar-nav ml-auto align-items-lg-center">
                        @if($headerCategoryActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#category-section' : '#category-section' }}">
                                    ক্যাটাগরি
                                </a>
                            </li>
                        @endif

                        @if($headerBrandActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#brand-section' : '#brand-section' }}">
                                    ব্র্যান্ড
                                </a>
                            </li>
                        @endif

                        @if($headerProductActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#products-section' : '#products-section' }}">
                                    প্রোডাক্ট
                                </a>
                            </li>
                        @endif

                        @if($headerDifferenceActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#difference-section' : '#difference-section' }}">
                                    পার্থক্য
                                </a>
                            </li>
                        @endif

                        @if($headerServiceActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#services-section' : '#services-section' }}">
                                    ফিচারস
                                </a>
                            </li>
                        @endif

                        @if($headerReviewActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#reviews-section' : '#reviews-section' }}">
                                    রিভিউ
                                </a>
                            </li>
                        @endif

                        @if($headerGalleryActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#gallery-section' : '#gallery-section' }}">
                                    গ্যালারি
                                </a>
                            </li>
                        @endif

                        @if($headerHelpActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#contact-section' : '#contact-section' }}">
                                    যোগাযোগ
                                </a>
                            </li>
                        @endif

                        @if($headerOrderTrackingActive)
                            <li class="nav-item">
                                <a class="nav-link" href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#order-track-section' : '#order-track-section' }}">
                                    অর্ডার ট্র্যাক করুন
                                </a>
                            </li>
                        @endif

                        @if($headerOrderActive)
                            <li class="nav-item ml-lg-3 d-none d-lg-block">
                                <a href="{{ $standaloneFrontendPage ? $standaloneCampaignUrl . '#order-section' : '#order-section' }}"
                                   class="btn btn-success order-nav-btn">
                                    <i class="fas fa-shopping-bag mr-1"></i>
                                    অর্ডার করুন
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
                <div class="front-offcanvas-backdrop" data-campaign-menu-backdrop hidden></div>
            </nav>
        @else
            {{-- Keep the original header shell/spacing, but render no visible data. --}}
            <nav class="navbar navbar-expand-lg front-navbar px-0"
                 aria-hidden="true"
                 data-empty-header="true">
                <div class="w-100"></div>
            </nav>
        @endif
    </div>
</header>
@endif