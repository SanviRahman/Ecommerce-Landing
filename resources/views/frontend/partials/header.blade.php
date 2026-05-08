@php
    $siteSetting = $siteSetting ?? \App\Models\SiteSetting::query()
        ->where('status', true)
        ->latest()
        ->first();

    $logo = null;

    if ($siteSetting && method_exists($siteSetting, 'getFirstMediaUrl')) {
        $logo = $siteSetting->getFirstMediaUrl('site_logo');
    }

    $websiteName = $siteSetting->website_name ?? config('app.name', 'EcoEats');
@endphp

<header class="front-header">
    <div class="container">
        <nav class="navbar navbar-expand-lg front-navbar px-0">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $websiteName }}" class="site-logo-img">
                @else
                    <span class="brand-mark">
                        <i class="fas fa-seedling"></i>
                    </span>

                    <span class="brand-text">
                        {{ $websiteName }}
                    </span>
                @endif
            </a>

            <div class="mobile-action d-lg-none ml-auto mr-2">
                <a href="#order-section" class="btn btn-success btn-sm order-nav-btn">
                    <i class="fas fa-shopping-bag mr-1"></i>
                    অর্ডার করুন
                </a>
            </div>

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
                    <li class="nav-item">
                        <a class="nav-link" href="#category-section">ক্যাটাগরি</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#brand-section">ব্র্যান্ড</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#products-section">প্রোডাক্ট</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#difference-section">পার্থক্য</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#services-section">ফিচারস</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#reviews-section">রিভিউ</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#gallery-section">গ্যালারি</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#contact-section">যোগাযোগ</a>
                    </li>

                    <li class="nav-item ml-lg-3 d-none d-lg-block">
                        <a href="#order-section" class="btn btn-success order-nav-btn">
                            <i class="fas fa-shopping-bag mr-1"></i>
                            অর্ডার করুন
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</header>