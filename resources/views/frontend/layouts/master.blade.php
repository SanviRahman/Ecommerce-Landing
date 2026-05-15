<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', config('app.name', 'EcoEats'))</title>

    <meta name="description" content="@yield('meta_description', '')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Bootstrap 4 --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    {{-- Font Awesome --}}
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    {{-- Dynamic Tracking Scripts --}}
    @includeIf('frontend.partials.tracking-pixels')

    <style>
        :root {
            --front-green: #22c55e;
            --front-green-dark: #16a34a;
            --front-dark: #334155;
            --front-muted: #94a3b8;
            --front-footer: #0f172a;
            --front-border: #e5e7eb;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Noto Sans Bengali', Arial, sans-serif;
            background: #ffffff;
            color: var(--front-dark);
            margin: 0;
            padding: 0;
        }

        a {
            transition: .2s;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */
        .front-header {
            background: rgba(255, 255, 255, .98);
            border-bottom: 1px solid #eef2f7;
            position: sticky;
            top: 0;
            z-index: 9999;
            backdrop-filter: blur(10px);
        }

        .front-navbar {
            min-height: 66px;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--front-green);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            flex-shrink: 0;
        }

        .brand-text {
            color: var(--front-green-dark);
            font-size: 28px;
            font-weight: 900;
            line-height: 1;
        }

        .site-logo-img {
            max-height: 42px;
            max-width: 170px;
            object-fit: contain;
        }

        .navbar-nav .nav-link {
            color: #475569;
            font-size: 15px;
            font-weight: 800;
            padding-left: 13px !important;
            padding-right: 13px !important;
        }

        .navbar-nav .nav-link:hover {
            color: var(--front-green-dark);
        }

        .order-nav-btn {
            border-radius: 7px;
            font-weight: 900;
            padding: 9px 24px;
            background: var(--front-green);
            border-color: var(--front-green);
            color: #ffffff !important;
        }

        .order-nav-btn:hover {
            background: var(--front-green-dark);
            border-color: var(--front-green-dark);
        }

        .front-toggler {
            border: 2px solid #ef4444;
            border-radius: 0;
            width: 42px;
            height: 42px;
            padding: 8px;
            outline: none !important;
        }

        .front-toggler span {
            display: block;
            height: 2px;
            background: #334155;
            margin: 5px 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */
        .front-footer {
            background: var(--front-footer);
            color: #cbd5e1;
            padding: 70px 0 30px;
            margin-top: 0;
        }

        .footer-logo-img {
            max-height: 42px;
            max-width: 180px;
            object-fit: contain;
        }

        .footer-text {
            color: #94a3b8;
            line-height: 1.9;
            font-size: 15px;
        }

        .footer-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: 900;
            margin-bottom: 22px;
        }

        .footer-contact {
            color: #cbd5e1;
            margin-bottom: 13px;
            line-height: 1.7;
        }

        .footer-contact i {
            color: #94a3b8;
            width: 20px;
        }

        .footer-social-link {
            width: 42px;
            height: 42px;
            border-radius: 7px;
            background: #334155;
            color: #cbd5e1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            margin-bottom: 8px;
            text-decoration: none;
        }

        .footer-social-link:hover {
            background: var(--front-green);
            color: #ffffff;
            text-decoration: none;
        }

        .footer-bottom {
            border-top: 1px solid #334155;
            margin-top: 35px;
            padding-top: 25px;
            display: flex;
            justify-content: space-between;
            color: #94a3b8;
            font-size: 14px;
        }

        .footer-bottom a {
            color: #cbd5e1;
            text-decoration: underline;
        }

        @media (max-width: 991px) {
            .brand-text {
                font-size: 25px;
            }

            .navbar-collapse {
                background: #ffffff;
                padding: 12px 0 20px;
            }

            .navbar-nav .nav-link {
                padding: 9px 0 !important;
            }

            .footer-bottom {
                display: block;
                text-align: center;
            }

            .footer-bottom span {
                display: block;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 575px) {
            .site-logo-img {
                max-width: 135px;
            }

            .brand-text {
                font-size: 23px;
            }

            .mobile-action .btn {
                padding: 8px 13px;
                font-size: 13px;
            }

            .front-toggler {
                width: 39px;
                height: 39px;
            }
        }
    </style>

    @stack('css')
</head>

<body>

    @includeIf('frontend.partials.header')

    <main>
        @yield('content')
    </main>

    @includeIf('frontend.partials.footer')

    {{-- jQuery --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    {{-- Bootstrap 4 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    {{-- DataLayer + Meta/TikTok Event Bridge --}}
    @includeIf('frontend.partials.datalayer-events')

    @stack('js')
</body>
</html>