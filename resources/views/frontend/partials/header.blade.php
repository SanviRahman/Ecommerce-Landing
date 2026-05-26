@php
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

    /*
    |--------------------------------------------------------------------------
    | Dynamic Logo URL
    |--------------------------------------------------------------------------
    | Campaign page-e thakle logo click korle same campaign page-e thakbe.
    | Normal home page-e thakle home page-e jabe.
    */
    $logoUrl = route('home');

    if (isset($campaign) && $campaign && ! empty($campaign->slug)) {
        $logoUrl = route('campaign.show', $campaign->slug);
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic Front Header / Menu Visibility
    |--------------------------------------------------------------------------
    | Campaign form-er section active/inactive switch onujayi frontend navbar-er
    | related menu/header links show/hide hobe.
    */
    $sectionStatus = function (string $field, bool $default = true) use ($campaign): bool {
        if (! isset($campaign) || ! $campaign) {
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
        ?? ($sectionStatus('category_section_status') && $hasCollectionItems($categories ?? null));

    $headerBrandActive = $isBrandSectionActive
        ?? ($sectionStatus('category_section_status') && $hasCollectionItems($brands ?? null));

    $headerProductActive = $isProductSectionActive
        ?? $sectionStatus('product_section_status');

    $headerDifferenceActive = $isComparisonSectionActive
        ?? $sectionStatus('comparison_section_status');

    $headerServiceActive = $isServiceSectionActive
        ?? $sectionStatus('service_section_status');

    $headerReviewActive = $isReviewSectionActive
        ?? ($sectionStatus('review_section_status') && $hasCollectionItems($reviews ?? null));

    $headerGalleryHasItems = true;

    if (isset($campaignGalleryImages)) {
        $headerGalleryHasItems = $hasCollectionItems($campaignGalleryImages);
    } elseif (isset($campaign) && $campaign && method_exists($campaign, 'getMedia')) {
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
@endphp

<header class="front-header">
    <div class="container">
        <nav class="navbar navbar-expand-lg front-navbar px-0">
            <a class="navbar-brand d-flex align-items-center" href="{{ $logoUrl }}">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $websiteName }}" class="site-logo-img">
                @else
                    <span class="font-weight-bold text-success">{{ $websiteName }}</span>
                @endif
            </a>

            @if($headerOrderActive)
                <div class="mobile-action d-lg-none ml-auto mr-2">
                    <a href="#order-section" class="btn btn-success btn-sm order-nav-btn">
                        <i class="fas fa-shopping-bag mr-1"></i>
                        অর্ডার করুন
                    </a>
                </div>
            @endif

            <button class="navbar-toggler front-toggler"
                    type="button"
                    data-toggle="collapse"
                    data-target="#frontMenu"
                    aria-controls="frontMenu"
                    aria-expanded="false"
                    aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="collapse navbar-collapse" id="frontMenu">
                <ul class="navbar-nav ml-auto align-items-lg-center">
                    @if($headerCategoryActive)
                        <li class="nav-item">
                            <a class="nav-link" href="#category-section">ক্যাটাগরি</a>
                        </li>
                    @endif

                    @if($headerBrandActive)
                        <li class="nav-item">
                            <a class="nav-link" href="#brand-section">ব্র্যান্ড</a>
                        </li>
                    @endif

                    @if($headerProductActive)
                        <li class="nav-item">
                            <a class="nav-link" href="#products-section">প্রোডাক্ট</a>
                        </li>
                    @endif

                    @if($headerDifferenceActive)
                        <li class="nav-item">
                            <a class="nav-link" href="#difference-section">পার্থক্য</a>
                        </li>
                    @endif

                    @if($headerServiceActive)
                        <li class="nav-item">
                            <a class="nav-link" href="#services-section">ফিচারস</a>
                        </li>
                    @endif

                    @if($headerReviewActive)
                        <li class="nav-item">
                            <a class="nav-link" href="#reviews-section">রিভিউ</a>
                        </li>
                    @endif

                    @if($headerGalleryActive)
                        <li class="nav-item">
                            <a class="nav-link" href="#gallery-section">গ্যালারি</a>
                        </li>
                    @endif

                    @if($headerHelpActive)
                        <li class="nav-item">
                            <a class="nav-link" href="#contact-section">যোগাযোগ</a>
                        </li>
                    @endif

                    @if($headerOrderActive)
                        <li class="nav-item ml-lg-3 d-none d-lg-block">
                            <a href="#order-section" class="btn btn-success order-nav-btn">
                                <i class="fas fa-shopping-bag mr-1"></i>
                                অর্ডার করুন
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </nav>
    </div>
</header>
