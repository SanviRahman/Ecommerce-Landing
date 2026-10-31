<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', config('app.name'))</title>

    <meta name="description" content="@yield('meta_description', '')">

    {{-- Dynamic Favicon From Site Settings --}}
    @php
        $activeSiteSetting = $siteSetting ?? null;

        if (! $activeSiteSetting && class_exists(\App\Models\SiteSetting::class)) {
            try {
                $activeSiteSetting = \App\Models\SiteSetting::where('status', true)->latest()->first();
            } catch (\Throwable $e) {
                $activeSiteSetting = null;
            }
        }

        $faviconUrl = null;

        try {
            if ($activeSiteSetting && method_exists($activeSiteSetting, 'getFirstMediaUrl')) {
                $faviconUrl = $activeSiteSetting->getFirstMediaUrl('site_favicon') ?: null;
            }
        } catch (\Throwable $e) {
            $faviconUrl = null;
        }

        try {
            if (! $faviconUrl && $activeSiteSetting && ! empty($activeSiteSetting->favicon)) {
                $faviconUrl = $activeSiteSetting->favicon;
            }
        } catch (\Throwable $e) {
            $faviconUrl = null;
        }

        $faviconVersion = optional($activeSiteSetting?->updated_at)->timestamp ?? time();
    @endphp

    @if($faviconUrl)
        <link rel="icon" type="image/png" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}?v={{ $faviconVersion }}">
    @endif

    {{-- Page Specific Head Content --}}
    @stack('head')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Bootstrap 4 --}}
    <link rel="stylesheet"
          href="{{ asset('frontend/vendor/bootstrap-4.6.1.css') }}?v=20261024-frontend2">

    {{-- Font Awesome --}}
    <link rel="stylesheet"
          href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">

    {{-- Capture image errors before content loads. All handler code lives in public/. --}}
    <script src="{{ asset('frontend/js/image-fallback.js') }}?v=20261024-frontend2"></script>

    {{-- Dynamic Tracking Scripts --}}
    @includeIf('frontend.partials.tracking-pixels')

    <link rel="stylesheet" href="{{ asset('frontend/css/layout.css') }}?v=20261024-frontend2">

    <link rel="stylesheet" href="{{ asset('frontend/css/footer.css') }}?v=20261024-frontend2">
    <link rel="stylesheet" href="{{ asset('frontend/css/back-to-top.css') }}">

    @stack('css')

    {{-- Loaded last so polished green actions override older page rules. --}}
    <link rel="stylesheet" href="{{ asset('frontend/css/buttons.css') }}?v=20261024-frontend2">
    <link rel="stylesheet" href="{{ asset('frontend/css/public-description-p53.css') }}?v=p53">
    <link rel="stylesheet" href="{{ asset('frontend/css/campaign-offcanvas-p62.css') }}?v=p62">
</head>

<body>

    @includeIf('frontend.partials.header')

    <main>
        @yield('content')
    </main>

    @includeIf('frontend.partials.footer')

    {{-- jQuery --}}
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>

    {{-- Bootstrap 4 JS --}}
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    {{-- DataLayer + Meta/TikTok Event Bridge --}}
    @includeIf('frontend.partials.datalayer-events')

    @stack('js')
    <script src="{{ asset('frontend/js/campaign-offcanvas-p62.js') }}?v=p62" defer></script>
    <script src="{{ asset('frontend/js/back-to-top.js') }}" defer></script>
</body>
</html>