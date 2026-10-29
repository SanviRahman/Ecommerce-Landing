<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if(($storefrontFavicon = app(\App\Support\StorefrontBranding::class)->favicon()))
        <link rel="icon" href="{{ $storefrontFavicon }}">
    @endif
    @if(request()->attributes->get('p26_isolated_commerce_qa', false) === true)
        <meta name="robots" content="noindex,nofollow">
    @endif
    <meta name="theme-color" content="#21653f">
    <title>@yield('title', 'Shop') — {{ config('app.name', 'Storefront') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-catalog.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-contact.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-next.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-premium.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @unless(request()->attributes->get('p25_local_readonly_browse', false))
        @include('storefront.partials.meta-pixel')
    @endunless
    @if(config('storefront.p08_cart_test_enabled', false))
        <link rel="stylesheet" href="{{ asset('frontend/css/storefront-cart.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p34.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p35.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p36.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p37.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p38.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p39.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p40.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p41.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p44.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p47.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/back-to-top.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p50.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p51.css') }}?v=p51">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p52.css') }}?v=p52">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p53.css') }}?v=p53">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p54.css') }}?v=p60">
    {{-- P60 header-only safety rules: protect against a stale external CSS copy in the cPanel document root. --}}
    <style>
        @media (min-width: 761px) {
            body.p34-storefront .p59-header-menu { display: none !important; }
        }
        @media (max-width: 760px) {
            body.p34-storefront .p19-benefit-bar { display: none !important; }
            body.p34-storefront .p19-main-header > .p19-search { display: none !important; }
            body.p34-storefront .p19-main-header {
                display: flex !important;
                flex-wrap: nowrap !important;
                align-items: center;
                gap: 7px;
                min-height: 62px;
                padding-top: 8px;
                padding-bottom: 8px;
            }
            body.p34-storefront .p59-header-menu {
                display: grid !important;
                place-items: center;
                flex: 0 0 40px;
                width: 40px;
                height: 44px;
                padding: 8px;
                color: #154b36;
                background: #fff;
                border: 1px solid #d1e3d7;
                border-radius: 10px;
                cursor: pointer;
            }
            body.p34-storefront .p19-main-header > .p07-brand {
                flex: 1 1 auto;
                min-width: 0;
                max-width: none !important;
                overflow: hidden;
            }
            body.p34-storefront .p33-header-actions {
                display: flex !important;
                flex: 0 0 auto;
                align-items: center;
                gap: 2px;
            }
            body.p34-storefront .p33-header-actions :is(.p19-account-link, .p19-top-cart) {
                display: grid !important;
                place-items: center;
                flex: 0 0 36px;
                width: 36px;
                min-width: 36px;
                min-height: 44px;
                padding: 5px;
            }
            body.p34-storefront .p33-header-actions .p33-action-copy { display: none !important; }
            body.p34-storefront .p19-mobile-search-panel {
                top: calc(62px + env(safe-area-inset-top));
            }
        }
        @media (max-width: 355px) {
            body.p34-storefront .p33-brand-name { font-size: 12px; }
            body.p34-storefront .p33-brand-name small { font-size: 7.5px; white-space: nowrap; }
        }
    </style>
    <script src="{{ asset('frontend/js/storefront-p51-loader.js') }}?v=p52"></script>
</head>
<body class="p34-storefront">
    @stack('p53-meta-noscript')
    @include('storefront.partials.page-loader')
    <a class="p07-skip" href="#main">Skip to content</a>
    @if(request()->attributes->get('p25_local_readonly_browse', false))
        <div class="p25-readonly-notice" role="status">
            <strong>LOCAL ADMIN STOREFRONT PREVIEW</strong> — Browsing only; no customer login, cart or checkout.
            <a href="{{ route('admin.storefront.home.index') }}">Back to Homepage Builder</a>
        </div>
    @endif
    @include('storefront.partials.navigation', ['navigation' => $navigation])
    <main id="main" class="p07-container">@yield('content')</main>
    @include('storefront.partials.footer')
    @include('storefront.partials.contact-widget')
    @unless(request()->attributes->get('p25_local_readonly_browse', false))
        @include('frontend.partials.datalayer-events')
        @if(($p53FlashedItem = session('p53_added_item')) && is_array($p53FlashedItem))
            <div hidden data-p53-added-item="{{ json_encode($p53FlashedItem, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}"></div>
        @endif
    @endunless
    <script src="{{ asset('frontend/js/storefront-catalog.js') }}" defer></script>
    <script src="{{ asset('frontend/js/storefront-p35.js') }}" defer></script>
    <script src="{{ asset('frontend/js/storefront-next.js') }}" defer></script>
    <script src="{{ asset('frontend/js/storefront-premium.js') }}" defer></script>
<script src="{{ asset('frontend/js/storefront-p38.js') }}" defer></script>
    <script src="{{ asset('frontend/js/storefront-p39.js') }}" defer></script>
    <script src="{{ asset('frontend/js/back-to-top.js') }}" defer></script>
    <script src="{{ asset('frontend/js/storefront-p52.js') }}?v=p52" defer></script>
    <script src="{{ asset('frontend/js/storefront-p53.js') }}?v=p53" defer></script>
</body>
</html>
