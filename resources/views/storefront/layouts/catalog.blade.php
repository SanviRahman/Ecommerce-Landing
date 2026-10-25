<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#21653f">
    <title>@yield('title', 'Shop') — {{ config('app.name', 'Storefront') }}</title>
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-catalog.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-contact.css') }}">
    @if(config('storefront.p08_cart_test_enabled', false))
        <link rel="stylesheet" href="{{ asset('frontend/css/storefront-cart.css') }}">
    @endif
</head>
<body>
    <a class="p07-skip" href="#main">Skip to content</a>
    @include('storefront.partials.navigation', ['navigation' => $navigation])
    <main id="main" class="p07-container">@yield('content')</main>
    @include('storefront.partials.footer')
    @include('storefront.partials.contact-widget')
    <script src="{{ asset('frontend/js/storefront-catalog.js') }}" defer></script>
</body>
</html>
