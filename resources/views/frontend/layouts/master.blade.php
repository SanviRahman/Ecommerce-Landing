<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', config('app.name', 'Website'))
    </title>

    <meta name="description" content="@yield('meta_description', '')">

    {{-- Bootstrap 4 --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    {{-- Font Awesome --}}
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    {{-- Dynamic Tracking Scripts --}}
    {{-- Meta Pixel / GTM / TikTok / Google Analytics scripts will render from database --}}
    @include('frontend.partials.tracking-pixels')

    @stack('css')
</head>

<body>

    {{-- Header --}}
    @includeIf('frontend.partials.header')

    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    @includeIf('frontend.partials.footer')

    {{-- jQuery + Bootstrap 4 --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    @stack('js')

</body>
</html>