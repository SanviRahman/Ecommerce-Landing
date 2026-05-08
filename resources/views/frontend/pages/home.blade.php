@extends('frontend.layouts.master')

@php
    $websiteName = $siteSetting->website_name ?? config('app.name', 'EcoEats');

    $pageTitle = $campaign?->meta_title
        ?: $campaign?->title
        ?: $websiteName;

    $pageDescription = $campaign?->meta_description
        ?: $siteSetting?->business_short_description
        ?: 'Premium ecommerce landing page.';

    $imageOf = function ($model, $fallback = null) {
        $fallback = $fallback ?: asset('vendor/adminlte/dist/img/no-image.png');

        if (! $model) {
            return $fallback;
        }

        foreach ([
            'image_url',
            'thumbnail',
            'thumbnail_url',
            'banner_image_url',
            'image_one_url',
            'image_two_url',
            'image_three_url',
            'review_image_url',
            'logo',
        ] as $attribute) {
            try {
                if (! empty($model->{$attribute})) {
                    return $model->{$attribute};
                }
            } catch (\Throwable $e) {
                //
            }
        }

        if (method_exists($model, 'getFirstMediaUrl')) {
            foreach ([
                'image',
                'images',
                'product_image',
                'product_images',
                'product_thumbnail',
                'product_gallery',
                'thumbnail',
                'banner_image',
                'image_one',
                'image_two',
                'image_three',
                'review_image',
                'site_logo',
            ] as $collection) {
                try {
                    $url = $model->getFirstMediaUrl($collection);

                    if ($url) {
                        return $url;
                    }
                } catch (\Throwable $e) {
                    //
                }
            }
        }

        return $fallback;
    };

    $heroImage = $campaign
        ? ($campaign->banner_image_url ?: $campaign->image_one_url)
        : asset('vendor/adminlte/dist/img/no-image.png');

    $heroTitle = $campaign?->title ?: 'খুলনার বিখ্যাত চুইঝাল!';

    $heroSubtitle = $campaign?->short_description
        ?: 'ঘরে বসেই অর্ডার করুন পছন্দের প্রিমিয়াম পণ্য। ক্যাশ অন ডেলিভারি এবং দ্রুত ডেলিভারি সুবিধা।';

    $categories = $categories ?? collect();
    $brands = $brands ?? collect();
    $products = $products ?? collect();
    $orderProducts = $orderProducts ?? collect();
    $reviews = $reviews ?? collect();
    $faqs = $faqs ?? collect();
    $courierServices = $courierServices ?? config('couriers.list', []);

    $reviewChunks = $reviews->chunk(3);
@endphp

@section('title', $pageTitle)
@section('meta_description', $pageDescription)

@push('css')
<style>
    :root {
        --front-green: #22c55e;
        --front-green-dark: #16a34a;
        --front-dark: #334155;
        --front-muted: #94a3b8;
        --front-light: #f8fafc;
        --front-border: #e5e7eb;
        --front-footer: #0f172a;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: 'Noto Sans Bengali', Arial, sans-serif;
        color: var(--front-dark);
        background: #ffffff;
    }

    a {
        transition: .2s;
    }

    .section-space {
        padding: 76px 0;
    }

    .section-title {
        color: var(--front-green);
        font-size: 34px;
        font-weight: 900;
        text-align: center;
        margin-bottom: 42px;
    }

    .hero-section {
        padding: 70px 0 55px;
        background: #ffffff;
    }

    .hero-title {
        color: var(--front-green);
        font-weight: 900;
        font-size: 40px;
        margin-bottom: 22px;
    }

    .hero-text {
        color: #64748b;
        font-size: 20px;
        line-height: 1.9;
    }

    .hero-check-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px 40px;
        margin: 35px 0;
    }

    .hero-check-item {
        color: #94a3b8;
        font-weight: 700;
        font-size: 18px;
    }

    .hero-check-item i {
        color: #94a3b8;
        margin-right: 8px;
    }

    .rating-stars {
        color: #f59e0b;
        font-size: 22px;
        margin-bottom: 8px;
    }

    .hero-rating-text {
        color: #f59e0b;
        font-size: 14px;
        font-weight: 700;
    }

    .hero-actions .btn {
        border-radius: 7px;
        padding: 13px 25px;
        font-weight: 800;
    }

    .hero-video-box {
        position: relative;
        overflow: hidden;
        max-width: 430px;
        margin-left: auto;
        border-radius: 0;
    }

    .hero-video-box img {
        width: 100%;
        height: 342px;
        object-fit: cover;
    }

    .play-btn {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        width: 86px;
        height: 86px;
        border-radius: 50%;
        border: 3px solid #ffffff;
        color: #ffffff;
        background: rgba(0,0,0,.20);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 34px;
    }

    .filter-card {
        background: #ffffff;
        border: 1px solid var(--front-border);
        border-radius: 10px;
        padding: 18px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .04);
    }

    .filter-title {
        font-weight: 900;
        color: #334155;
        font-size: 18px;
        margin-bottom: 12px;
    }

    .filter-chip {
        border: 1px solid #dbe4ef;
        background: #ffffff;
        color: #64748b;
        border-radius: 999px;
        padding: 8px 14px;
        font-weight: 700;
        font-size: 14px;
        margin: 0 8px 10px 0;
        cursor: pointer;
        transition: .2s;
    }

    .filter-chip.active,
    .filter-chip:hover {
        background: var(--front-green);
        border-color: var(--front-green);
        color: #ffffff;
    }

    .product-card {
        background: #ffffff;
        border: 1px solid var(--front-border);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .05);
        transition: .25s;
        height: 100%;
    }

    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 18px 40px rgba(15, 23, 42, .09);
    }

    .product-image {
        width: 100%;
        height: 210px;
        object-fit: cover;
        background: #f8fafc;
    }

    .product-body {
        padding: 18px;
    }

    .product-body h4 {
        font-size: 19px;
        font-weight: 900;
        color: #334155;
        margin-bottom: 8px;
    }

    .product-meta {
        color: #94a3b8;
        font-size: 14px;
        min-height: 22px;
    }

    .product-price {
        color: var(--front-green-dark);
        font-size: 22px;
        font-weight: 900;
        margin: 12px 0;
    }

    .product-price del {
        color: #94a3b8;
        font-size: 14px;
        margin-left: 8px;
        font-weight: 500;
    }

    .add-order-btn {
        border-radius: 7px;
        font-weight: 800;
    }

    .brand-strip,
    .category-strip {
        background: #ffffff;
    }

    .brand-card,
    .category-card {
        border: 1px solid var(--front-border);
        border-radius: 10px;
        padding: 18px 14px;
        text-align: center;
        font-weight: 900;
        color: #475569;
        background: #ffffff;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .04);
    }

    .brand-card i,
    .category-card i {
        color: var(--front-green);
        margin-right: 7px;
    }

    .difference-table {
        color: #64748b;
        font-size: 16px;
    }

    .difference-table td {
        border-color: #edf2f7;
        padding: 13px 15px;
        text-align: center;
    }

    .side-product-img {
        width: 100%;
        height: 260px;
        object-fit: cover;
        border-radius: 8px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
    }

    .service-card {
        text-align: center;
        padding: 25px 15px;
    }

    .service-icon {
        width: 82px;
        height: 82px;
        border-radius: 8px;
        background: #f8fafc;
        color: var(--front-green);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 22px;
    }

    .service-card h4 {
        font-size: 20px;
        font-weight: 900;
        color: #334155;
    }

    .service-card p {
        color: #94a3b8;
        font-size: 16px;
        line-height: 1.7;
    }

    .wide-banner {
        background: #f8fafc;
        border-radius: 8px;
        overflow: hidden;
        height: 245px;
        position: relative;
    }

    .wide-banner img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .review-carousel-wrapper {
        position: relative;
        padding-bottom: 45px;
    }

    .review-card {
        background: #f8fafc;
        border-radius: 8px;
        padding: 32px;
        min-height: 245px;
        height: 100%;
    }

    .review-card .stars {
        color: #f59e0b;
        margin-bottom: 20px;
    }

    .review-card p {
        color: #94a3b8;
        line-height: 1.8;
        font-size: 16px;
    }

    .review-user {
        display: flex;
        align-items: center;
        margin-top: 22px;
    }

    .review-user img {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 15px;
    }

    .review-user strong {
        display: block;
        color: #334155;
    }

    .review-user span {
        color: #94a3b8;
        font-size: 14px;
    }

    .review-social {
        margin-left: auto;
        color: #94a3b8;
        font-size: 20px;
    }

    .review-dots {
        position: static;
        margin-top: 22px;
        margin-bottom: 0;
        justify-content: center;
    }

    .review-dots li {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #cbd5e1;
        opacity: 1;
        border: 0;
        margin: 0 6px;
    }

    .review-dots li.active {
        background: var(--front-green);
    }

    .carousel-control-prev,
    .carousel-control-next {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #e2e8f0;
        top: 45%;
        opacity: 1;
    }

    .carousel-control-prev {
        left: -10px;
    }

    .carousel-control-next {
        right: -10px;
    }

    .faq-wrapper {
        max-width: 900px;
        margin: auto;
    }

    .faq-item {
        border-top: 1px solid #e5e7eb;
    }

    .faq-button {
        width: 100%;
        background: transparent;
        border: 0;
        padding: 25px 0;
        text-align: left;
        color: #334155;
        font-size: 20px;
        font-weight: 800;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .faq-body {
        color: #64748b;
        line-height: 1.8;
        padding-bottom: 22px;
    }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
    }

    .gallery-grid img {
        width: 100%;
        height: 220px;
        border-radius: 8px;
        object-fit: cover;
    }

    .order-section {
        background: #ffffff;
        padding: 80px 0;
    }

    .order-product-card {
        border: 1px solid var(--front-border);
        background: #f8fafc;
        border-radius: 9px;
        padding: 12px;
        display: flex;
        align-items: center;
        cursor: pointer;
        position: relative;
        transition: .2s;
        min-height: 95px;
    }

    .order-product-card.active {
        border-color: var(--front-green);
        box-shadow: 0 0 0 1px var(--front-green);
        background: #f0fdf4;
    }

    .order-product-card img {
        width: 78px;
        height: 68px;
        object-fit: cover;
        border-radius: 7px;
        margin-right: 12px;
    }

    .order-product-card h5 {
        font-size: 17px;
        font-weight: 900;
        color: #334155;
        margin-bottom: 5px;
    }

    .order-product-card p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .selected-check {
        position: absolute;
        right: 12px;
        top: 12px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--front-green);
        color: #ffffff;
        font-size: 12px;
        align-items: center;
        justify-content: center;
        display: none;
    }

    .order-product-card.active .selected-check {
        display: flex;
    }

    .checkout-title {
        font-size: 24px;
        font-weight: 900;
        margin-bottom: 25px;
        color: #334155;
    }

    .summary-line {
        border-top: 1px dashed #cbd5e1;
        padding: 16px 0;
        display: flex;
        justify-content: space-between;
        color: #94a3b8;
        font-size: 16px;
    }

    .summary-line strong {
        color: var(--front-green-dark);
    }

    .summary-product {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px dashed #cbd5e1;
        padding: 16px 0;
    }

    .summary-product-info {
        display: flex;
        align-items: center;
        max-width: 75%;
    }

    .summary-product-info img {
        width: 58px;
        height: 58px;
        object-fit: cover;
        border-radius: 7px;
        margin-right: 12px;
    }

    .summary-product-info h5 {
        font-size: 17px;
        font-weight: 900;
        color: var(--front-green-dark);
        margin-bottom: 4px;
    }

    .summary-product-info span {
        color: #94a3b8;
        font-size: 14px;
    }

    .summary-qty {
        width: 76px;
        height: 42px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        padding: 5px;
    }

    .remove-summary-item {
        color: #94a3b8;
        cursor: pointer;
        margin-right: 12px;
        font-size: 18px;
    }

    .remove-summary-item:hover {
        color: #ef4444;
    }

    .grand-total {
        font-size: 18px;
        font-weight: 900;
    }

    .checkout-form label {
        color: #94a3b8;
        font-weight: 700;
        font-size: 15px;
    }

    .checkout-form .form-control {
        min-height: 46px;
        border-color: #cbd5e1;
        border-radius: 7px;
    }

    .order-submit-btn {
        min-height: 58px;
        border-radius: 8px;
        font-size: 18px;
        font-weight: 900;
        background: var(--front-green);
        border-color: var(--front-green);
    }

    .help-box {
        background: linear-gradient(90deg, rgba(240,253,244,.95), rgba(255,255,255,.92)), url('{{ $heroImage }}');
        background-size: cover;
        background-position: center;
        border-radius: 8px;
        padding: 55px 20px;
        text-align: center;
    }

    .help-box h2 {
        font-weight: 900;
        color: #334155;
        margin-bottom: 15px;
    }

    .help-box p {
        color: #94a3b8;
        max-width: 780px;
        margin: 0 auto 25px;
        line-height: 1.8;
    }

    @media (max-width: 991px) {
        .hero-section {
            padding-top: 24px;
        }

        .hero-section .hero-content {
            display: none;
        }

        .hero-video-box {
            max-width: 100%;
            margin: 0;
            border-radius: 18px;
        }

        .hero-video-box img {
            height: 490px;
            border-radius: 18px;
        }

        .play-btn {
            width: 76px;
            height: 76px;
        }

        .section-space {
            padding: 55px 0;
        }

        .hero-check-list {
            grid-template-columns: repeat(2, 1fr);
        }

        .gallery-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .carousel-control-prev,
        .carousel-control-next {
            display: none;
        }
    }

    @media (max-width: 575px) {
        .container {
            padding-left: 16px;
            padding-right: 16px;
        }

        .hero-video-box img {
            height: 490px;
        }

        .section-title {
            font-size: 28px;
        }

        .gallery-grid {
            grid-template-columns: 1fr;
        }

        .gallery-grid img {
            height: 240px;
        }

        .order-product-card {
            margin-bottom: 12px;
        }

        .summary-product {
            align-items: flex-start;
        }

        .summary-product-info {
            max-width: 68%;
        }
    }
</style>
@endpush

@section('content')

@if (session('success'))
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show">
            <strong>Success!</strong> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="container mt-3">
        <div class="alert alert-danger">
            <strong>দয়া করে নিচের ভুলগুলো ঠিক করুন:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- Section 01: Hero --}}
<section class="hero-section" id="video-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7 hero-content">
                <h1 class="hero-title">{{ $heroTitle }}</h1>

                <div class="hero-text">
                    {!! $heroSubtitle !!}
                </div>

                <div class="hero-check-list">
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> গাছের মাছ</div>
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> হাঁসের মাংস</div>
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> মাছ</div>
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> দেশী</div>
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> খাসির মাংস</div>
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> মুরগী ঘন্ট</div>
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> সরিষা</div>
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> কালিজিরা</div>
                    <div class="hero-check-item"><i class="fas fa-check-circle"></i> চটপটি</div>
                </div>

                <div class="rating-stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                </div>

                <div class="hero-rating-text mb-4">
                    ৩০,০০০ হাজারও অধিক গ্রাহকের কাছে <br>
                    আমরা হয়েছি জনপ্রিয়
                </div>

                <div class="hero-actions">
                    <a href="#order-section" class="btn btn-success mr-2">
                        <i class="fas fa-shopping-cart mr-2"></i>
                        অর্ডার করুন
                    </a>

                    @if($siteSetting?->whatsapp_number)
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $siteSetting->whatsapp_number) }}" class="btn btn-secondary mr-2">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    @endif

                    @if($siteSetting?->phone)
                        <a href="tel:{{ $siteSetting->phone }}" class="btn btn-secondary">
                            <i class="fas fa-phone-alt"></i>
                        </a>
                    @endif
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hero-video-box">
                    <img src="{{ $heroImage }}" alt="{{ $heroTitle }}">
                    <div class="play-btn">
                        <i class="fas fa-play"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Category Section --}}
<section class="category-strip section-space" id="category-section">
    <div class="container">
        <h2 class="section-title">ক্যাটাগরি সমূহ</h2>

        <div class="row">
            @forelse($categories as $category)
                <div class="col-lg-3 col-md-4 col-6 mb-3">
                    <div class="category-card">
                        <i class="fas fa-leaf"></i>
                        {{ $category->name }}
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-warning text-center">কোনো category পাওয়া যায়নি।</div>
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- Brand Section --}}
<section class="brand-strip pb-5" id="brand-section">
    <div class="container">
        <h2 class="section-title">ব্র্যান্ড সমূহ</h2>

        <div class="row">
            @forelse($brands as $brand)
                <div class="col-lg-3 col-md-4 col-6 mb-3">
                    <div class="brand-card">
                        <i class="fas fa-store"></i>
                        {{ $brand->name }}
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-warning text-center">কোনো brand পাওয়া যায়নি।</div>
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- Product Section --}}
<section class="section-space bg-white" id="products-section">
    <div class="container">
        <h2 class="section-title">আমাদের প্রোডাক্ট</h2>

        <div class="filter-card mb-4">
            <div class="row">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="filter-title">ক্যাটাগরি দিয়ে ফিল্টার</div>
                    <button type="button" class="filter-chip active product-filter" data-filter-type="category" data-filter-value="all">
                        All Categories
                    </button>

                    @foreach($categories as $category)
                        <button type="button" class="filter-chip product-filter" data-filter-type="category" data-filter-value="{{ $category->id }}">
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>

                <div class="col-lg-6">
                    <div class="filter-title">ব্র্যান্ড দিয়ে ফিল্টার</div>
                    <button type="button" class="filter-chip active product-filter" data-filter-type="brand" data-filter-value="all">
                        All Brands
                    </button>

                    @foreach($brands as $brand)
                        <button type="button" class="filter-chip product-filter" data-filter-type="brand" data-filter-value="{{ $brand->id }}">
                            {{ $brand->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row" id="frontProductGrid">
            @forelse($products as $product)
                @php
                    $productImage = $imageOf($product);
                    $productPrice = (int) $product->new_price;
                    $productOldPrice = $product->old_price ? (int) $product->old_price : null;
                    $productWeight = $product->weight_size ?: '৫০০ গ্রাম';
                @endphp

                <div class="col-lg-3 col-md-4 col-sm-6 mb-4 product-grid-item"
                     data-category="{{ $product->category_id }}"
                     data-brand="{{ $product->brand_id ?: 'none' }}">
                    <div class="product-card">
                        <img src="{{ $productImage }}" class="product-image" alt="{{ $product->name }}">

                        <div class="product-body">
                            <h4>{{ \Illuminate\Support\Str::limit($product->name, 35) }}</h4>

                            <div class="product-meta">
                                {{ $productWeight }}
                            </div>

                            <div class="product-price">
                                ৳{{ number_format($productPrice) }}

                                @if($productOldPrice)
                                    <del>৳{{ number_format($productOldPrice) }}</del>
                                @endif
                            </div>

                            <button type="button"
                                    class="btn btn-success btn-block add-order-btn add-product-to-order"
                                    data-product-id="{{ $product->id }}">
                                <i class="fas fa-cart-plus mr-1"></i>
                                অর্ডারে যোগ করুন
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        কোনো active product পাওয়া যায়নি।
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- Section: Difference --}}
<section class="section-space difference-section" id="difference-section">
    <div class="container">
        <h2 class="section-title">চুইঝালের পার্থক্যসমূহ</h2>

        <div class="row align-items-center">
            <div class="col-lg-3 mb-4 mb-lg-0">
                <img src="{{ $campaign ? $campaign->image_one_url : $heroImage }}" class="side-product-img" alt="Product">
            </div>

            <div class="col-lg-6">
                <h4 class="text-center font-weight-bold mb-4">গাছ চুইঝাল &nbsp;&nbsp; এটা চুইঝাল</h4>

                <table class="table difference-table">
                    <tbody>
                    <tr>
                        <td>চুইঝাল গাছের কাঠকে গাছ চুইঝাল বলা হয়।</td>
                        <td>চুইঝাল গাছের গোড়া এবং গোড়া সংলগ্ন অংশকে এটা চুইঝাল বলা হয়।</td>
                    </tr>
                    <tr>
                        <td>গাছ চুইঝাল সাধারণত রান্নায় সহজে গলে যায়।</td>
                        <td>এটা চুইঝাল ফাইবার কম থাকায় রান্নায় ভালো ফ্লেভার দেয়।</td>
                    </tr>
                    <tr>
                        <td>রান্নায় ঝাঁজ ও ঘ্রাণ বাড়াতে ব্যবহার করা হয়।</td>
                        <td>মাংস, ডাল ও তরকারিতে ব্যবহার করা যায়।</td>
                    </tr>
                    <tr>
                        <td>সাধারণত বড় পরিমাণে ব্যবহার করা হয়।</td>
                        <td>মসলা হিসেবে স্বাদ ও ঘ্রাণ বাড়াতে ব্যবহার করা হয়।</td>
                    </tr>
                    <tr>
                        <td>এটি রান্নার স্বাদকে আলাদা করে তোলে।</td>
                        <td>এটি সাধারণ খাবারকেও সুস্বাদু করে তোলে।</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="col-lg-3">
                <img src="{{ $campaign ? $campaign->image_two_url : $heroImage }}" class="side-product-img" alt="Product">
            </div>
        </div>
    </div>
</section>

{{-- Service Section --}}
<section class="section-space" id="services-section">
    <div class="container">
        <h2 class="section-title">কেন আমরাই সেরা</h2>

        <div class="row">
            <div class="col-md-3 col-6">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-award"></i></div>
                    <h4>অর্গানিক প্রোডাক্ট</h4>
                    <p>আমাদের কাছে পাবেন সেরা মানের প্রিমিয়াম পণ্য।</p>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-crown"></i></div>
                    <h4>প্রিমিয়াম কোয়ালিটি</h4>
                    <p>সেরা কোয়ালিটির পণ্য সংগ্রহ করে সরবরাহ করা হয়।</p>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-undo-alt"></i></div>
                    <h4>রিটার্ন পলিসি</h4>
                    <p>সমস্যা হলে সহজ রিটার্ন ও রিপ্লেসমেন্ট সুবিধা।</p>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-truck"></i></div>
                    <h4>ক্যাশ অন ডেলিভারি</h4>
                    <p>পণ্য হাতে পেয়ে টাকা পরিশোধ করার সুবিধা।</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Wide Banner --}}
<section class="pb-5">
    <div class="container">
        <div class="wide-banner">
            <img src="{{ $campaign ? $campaign->image_three_url : $heroImage }}" alt="Banner">
        </div>
    </div>
</section>

{{-- Review Auto Slider --}}
<section class="section-space" id="reviews-section">
    <div class="container">
        <h2 class="section-title">কাস্টমার রিভিউ</h2>

        <div class="review-carousel-wrapper">
            @if($reviewChunks->isNotEmpty())
                <div id="reviewCarousel" class="carousel slide" data-ride="carousel" data-interval="2800" data-pause="hover">
                    <div class="carousel-inner">
                        @foreach($reviewChunks as $chunkIndex => $chunk)
                            <div class="carousel-item {{ $chunkIndex === 0 ? 'active' : '' }}">
                                <div class="row">
                                    @foreach($chunk as $review)
                                        @php
                                            $rating = (int) ($review->rating ?: 5);
                                            $rating = $rating < 1 ? 5 : ($rating > 5 ? 5 : $rating);
                                            $reviewImage = $imageOf($review, asset('vendor/adminlte/dist/img/user2-160x160.jpg'));
                                        @endphp

                                        <div class="col-lg-4 mb-4">
                                            <div class="review-card">
                                                <div class="stars">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <i class="{{ $i <= $rating ? 'fas' : 'far' }} fa-star"></i>
                                                    @endfor
                                                </div>

                                                <p>
                                                    {{ $review->review_text ?: 'প্রোডাক্টের কোয়ালিটি ভালো ছিল এবং ডেলিভারিও দ্রুত হয়েছে। ধন্যবাদ।' }}
                                                </p>

                                                <div class="review-user">
                                                    <img src="{{ $reviewImage }}" alt="{{ $review->customer_name }}">

                                                    <div>
                                                        <strong>{{ $review->customer_name }}</strong>
                                                        <span>{{ $review->location ?: 'ঢাকা' }}</span>
                                                    </div>

                                                    @if($review->social_link)
                                                        <a href="{{ $review->social_link }}" target="_blank" class="review-social">
                                                            <i class="fab fa-facebook-f"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($reviewChunks->count() > 1)
                        <a class="carousel-control-prev" href="#reviewCarousel" role="button" data-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </a>

                        <a class="carousel-control-next" href="#reviewCarousel" role="button" data-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </a>

                        <ol class="carousel-indicators review-dots">
                            @foreach($reviewChunks as $chunkIndex => $chunk)
                                <li data-target="#reviewCarousel"
                                    data-slide-to="{{ $chunkIndex }}"
                                    class="{{ $chunkIndex === 0 ? 'active' : '' }}">
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @else
                <div id="reviewCarousel" class="carousel slide" data-ride="carousel" data-interval="2800">
                    <div class="carousel-inner">
                        @for($slide = 0; $slide < 2; $slide++)
                            <div class="carousel-item {{ $slide === 0 ? 'active' : '' }}">
                                <div class="row">
                                    @for($i = 1; $i <= 3; $i++)
                                        <div class="col-lg-4 mb-4">
                                            <div class="review-card">
                                                <div class="stars">
                                                    <i class="fas fa-star"></i>
                                                    <i class="fas fa-star"></i>
                                                    <i class="fas fa-star"></i>
                                                    <i class="fas fa-star"></i>
                                                    <i class="fas fa-star"></i>
                                                </div>

                                                <p>প্রোডাক্টের কোয়ালিটি ভালো ছিল এবং ডেলিভারিও দ্রুত হয়েছে। ধন্যবাদ।</p>

                                                <div class="review-user">
                                                    <img src="{{ asset('vendor/adminlte/dist/img/user2-160x160.jpg') }}" alt="Customer">
                                                    <div>
                                                        <strong>সন্তুষ্ট গ্রাহক</strong>
                                                        <span>ঢাকা</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        @endfor
                    </div>

                    <ol class="carousel-indicators review-dots">
                        <li data-target="#reviewCarousel" data-slide-to="0" class="active"></li>
                        <li data-target="#reviewCarousel" data-slide-to="1"></li>
                    </ol>
                </div>
            @endif
        </div>
    </div>
</section>

{{-- FAQ --}}
<section class="section-space" id="faq-section">
    <div class="container">
        <h2 class="section-title">সচরাচর জিজ্ঞাস্য প্রশ্নাবলি</h2>

        <div class="faq-wrapper">
            @forelse($faqs as $faq)
                <div class="faq-item">
                    <button class="faq-button" type="button" data-toggle="collapse" data-target="#faq{{ $faq->id }}">
                        <span>{{ $faq->question }}</span>
                        <i class="fas fa-plus"></i>
                    </button>

                    <div class="collapse faq-body" id="faq{{ $faq->id }}">
                        {!! $faq->answer !!}
                    </div>
                </div>
            @empty
                @foreach([
                    'চুইঝাল কি?',
                    'এক কেজি মাংসে কতটুকু চুইঝাল দিব?',
                    'চুইঝাল কিভাবে কাটবো?',
                    'চুইঝালের রান্নায় বিশেষত্ব কি?',
                    'কিভাবে রান্না করব চুইঝাল দিয়ে?',
                    'গাছ ও এটা চুইঝালের মধ্যে পার্থক্য কি?',
                    'দেশি ও পাহাড়ী চুইঝাল এর পার্থক্য কি?',
                    'কি কি ঔষধি গুণ রয়েছে চুইঝালে?'
                ] as $index => $question)
                    <div class="faq-item">
                        <button class="faq-button" type="button" data-toggle="collapse" data-target="#staticFaq{{ $index }}">
                            <span>{{ $question }}</span>
                            <i class="fas fa-plus"></i>
                        </button>

                        <div class="collapse faq-body" id="staticFaq{{ $index }}">
                            এই প্রশ্নের বিস্তারিত উত্তর এখানে যুক্ত করুন।
                        </div>
                    </div>
                @endforeach
            @endforelse
        </div>
    </div>
</section>

{{-- Gallery --}}
<section class="section-space" id="gallery-section">
    <div class="container">
        <h2 class="section-title">প্রোডাক্ট গ্যালারি</h2>

        <div class="gallery-grid">
            @if($campaign)
                @if($campaign->image_one_url)
                    <img src="{{ $campaign->image_one_url }}" alt="Gallery">
                @endif

                @if($campaign->image_two_url)
                    <img src="{{ $campaign->image_two_url }}" alt="Gallery">
                @endif

                @if($campaign->image_three_url)
                    <img src="{{ $campaign->image_three_url }}" alt="Gallery">
                @endif

                @if($campaign->banner_image_url)
                    <img src="{{ $campaign->banner_image_url }}" alt="Gallery">
                @endif
            @endif

            @foreach($products->take(8) as $product)
                <img src="{{ $imageOf($product) }}" alt="{{ $product->name }}">
            @endforeach
        </div>
    </div>
</section>

{{-- Order --}}
<section class="order-section" id="order-section">
    <div class="container">
        <h2 class="section-title">অর্ডার করুন এখনই</h2>

        @if($campaign && $orderProducts->isNotEmpty())
            <form action="{{ route('campaign.order.store', $campaign->slug) }}" method="POST" id="landingOrderForm">
                @csrf

                <div id="selectedProductsInputs"></div>

                <div class="row">
                    <div class="col-lg-6 mb-5 mb-lg-0">
                        <h3 class="checkout-title">প্রোডাক্ট নির্বাচন করুন</h3>

                        <div class="row">
                            @foreach($orderProducts as $product)
                                @php
                                    $unitPrice = (int) ($product->pivot->campaign_price ?? 0);
                                    $unitPrice = $unitPrice > 0 ? $unitPrice : (int) $product->new_price;
                                    $weight = $product->weight_size ?: '৫০০ গ্রাম';
                                    $productImage = $imageOf($product);
                                @endphp

                                <div class="col-md-6 mb-3">
                                    <div class="order-product-card {{ $loop->first ? 'active' : '' }}"
                                         data-id="{{ $product->id }}"
                                         data-name="{{ e($product->name) }}"
                                         data-price="{{ $unitPrice }}"
                                         data-weight="{{ e($weight) }}"
                                         data-image="{{ e($productImage) }}">
                                        <span class="selected-check">
                                            <i class="fas fa-check"></i>
                                        </span>

                                        <img src="{{ $productImage }}" alt="{{ $product->name }}">

                                        <div>
                                            <h5>{{ \Illuminate\Support\Str::limit($product->name, 22) }}</h5>
                                            <p>
                                                ৳ {{ number_format($unitPrice) }}
                                                <span class="float-right ml-2">{{ $weight }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="checkout-form mt-4">
                            <h3 class="checkout-title">ডেলিভারি এড্রেস</h3>

                            <div class="form-group">
                                <label>আপনার নাম</label>
                                <input type="text" name="customer_name" value="{{ old('customer_name') }}" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>মোবাইল নাম্বার</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>আপনার ঠিকানা</label>
                                <textarea name="address" rows="3" class="form-control" required>{{ old('address') }}</textarea>
                            </div>

                            <div class="form-group">
                                <label>ডেলিভারি এরিয়া</label>
                                <select name="delivery_area" id="deliveryArea" class="form-control" required>
                                    <option value="inside_dhaka" @selected(old('delivery_area', 'inside_dhaka') === 'inside_dhaka')>
                                        Inside Dhaka - ঢাকা ভেতরে - ৳৭০
                                    </option>
                                    <option value="outside_dhaka" @selected(old('delivery_area') === 'outside_dhaka')>
                                        Outside Dhaka - ঢাকার বাইরে - ৳১৩০
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>কুরিয়ার সার্ভিস</label>
                                <select name="courier_service" class="form-control" required>
                                    <option value="">কুরিয়ার সার্ভিস নির্বাচন করুন</option>
                                    @foreach($courierServices as $key => $label)
                                        <option value="{{ $key }}" @selected(old('courier_service') === $key)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label>নোট / কালার / সাইজ</label>
                                <textarea name="customer_note" rows="2" class="form-control">{{ old('customer_note') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <h3 class="checkout-title">অর্ডার সামারি</h3>

                        <div class="summary-box" id="orderSummaryItems"></div>

                        <div class="summary-line">
                            <span>মোট</span>
                            <strong id="summarySubTotal">0.00 tk</strong>
                        </div>

                        <div class="summary-line">
                            <span>ডেলিভারি চার্জ</span>
                            <strong id="summaryDeliveryCharge">0.00 tk</strong>
                        </div>

                        <div class="summary-line">
                            <span>ক্যাশ অন ডেলিভারি চার্জ ০%</span>
                            <strong id="summaryCodCharge">0.00 tk</strong>
                        </div>

                        <div class="summary-line grand-total">
                            <span>সর্বমোট</span>
                            <strong id="summaryGrandTotal">0.00 tk</strong>
                        </div>

                        <div class="summary-line">
                            <span>ডেলিভারি মেথড</span>
                            <strong>ক্যাশ অন ডেলিভারি</strong>
                        </div>

                        <button type="submit" class="btn btn-success btn-block order-submit-btn" id="orderSubmitBtn">
                            <i class="far fa-check-circle mr-2"></i>
                            অর্ডার প্লেস করুন
                        </button>

                        <p class="text-center mt-4 text-muted">
                            বিশেষ দ্রষ্টব্য: ক্যাশ অন ডেলিভারি নেওয়ার সময় পণ্য মূল্য পরিশোধ করুন।
                        </p>
                    </div>
                </div>
            </form>
        @else
            <div class="alert alert-warning text-center">
                কোনো active campaign product পাওয়া যায়নি। আগে admin panel থেকে active campaign এবং product attach করুন।
            </div>
        @endif
    </div>
</section>

{{-- Help CTA --}}
<section class="pb-5">
    <div class="container">
        <div class="help-box">
            <h2>সাহায্য প্রয়োজন?</h2>
            <p>
                কোনো জিজ্ঞাসা বা অর্ডার সংক্রান্ত সমস্যা হলে আমাদের হেল্পলাইনে কল করুন অথবা মেসেজ করুন।
            </p>

            @if($siteSetting?->phone)
                <a href="tel:{{ $siteSetting->phone }}" class="btn btn-success px-4 mr-2">
                    <i class="fas fa-phone-alt mr-2"></i>
                    হেল্পলাইন
                </a>
            @endif

            @if($siteSetting?->whatsapp_number)
                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $siteSetting->whatsapp_number) }}" class="btn btn-dark px-4 mr-2">
                    <i class="fab fa-whatsapp"></i>
                </a>
            @endif

            @if($siteSetting?->messenger_link)
                <a href="{{ $siteSetting->messenger_link }}" class="btn btn-dark px-4">
                    <i class="fab fa-facebook-messenger"></i>
                </a>
            @endif
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
$(document).ready(function () {
    const deliveryCharges = {
        inside_dhaka: 70,
        outside_dhaka: 130
    };

    let selectedCategory = 'all';
    let selectedBrand = 'all';
    let selectedProducts = {};

    function money(amount) {
        amount = Number(amount || 0);

        return amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' tk';
    }

    function addProductFromCard(card) {
        const id = String(card.data('id'));

        selectedProducts[id] = {
            id: id,
            name: card.data('name'),
            price: Number(card.data('price') || 0),
            weight: card.data('weight'),
            image: card.data('image'),
            quantity: selectedProducts[id] ? selectedProducts[id].quantity : 1
        };
    }

    $('.order-product-card.active').each(function () {
        addProductFromCard($(this));
    });

    function renderSummary() {
        let subTotal = 0;
        let itemsHtml = '';
        let inputsHtml = '';
        let index = 0;

        Object.values(selectedProducts).forEach(function (item) {
            const lineTotal = item.price * item.quantity;
            subTotal += lineTotal;

            itemsHtml += `
                <div class="summary-product" data-id="${item.id}">
                    <div class="summary-product-info">
                        <img src="${item.image}" alt="${item.name}">
                        <div>
                            <h5>${item.name}</h5>
                            <span>${item.weight}</span><br>
                            <strong>৳ ${Number(item.price).toLocaleString('en-US')}</strong>
                        </div>
                    </div>

                    <div class="d-flex align-items-center">
                        <span class="remove-summary-item" data-id="${item.id}">
                            <i class="fas fa-trash-alt"></i>
                        </span>

                        <select class="summary-qty" data-id="${item.id}">
                            ${[1,2,3,4,5,6,7,8,9,10].map(function(qty) {
                                return `<option value="${qty}" ${qty === item.quantity ? 'selected' : ''}>${qty}</option>`;
                            }).join('')}
                        </select>
                    </div>
                </div>
            `;

            inputsHtml += `
                <input type="hidden" name="products[${index}][id]" value="${item.id}">
                <input type="hidden" name="products[${index}][quantity]" value="${item.quantity}">
            `;

            index++;
        });

        const deliveryArea = $('#deliveryArea').val();
        const deliveryCharge = deliveryCharges[deliveryArea] || 0;
        const codCharge = 0;
        const grandTotal = subTotal + deliveryCharge + codCharge;

        $('#orderSummaryItems').html(itemsHtml);
        $('#selectedProductsInputs').html(inputsHtml);

        $('#summarySubTotal').text(money(subTotal));
        $('#summaryDeliveryCharge').text(money(deliveryCharge));
        $('#summaryCodCharge').text(money(codCharge));
        $('#summaryGrandTotal').text(money(grandTotal));

        $('#orderSubmitBtn').prop('disabled', Object.keys(selectedProducts).length === 0);
    }

    function syncOrderCards() {
        $('.order-product-card').each(function () {
            const card = $(this);
            const id = String(card.data('id'));

            if (selectedProducts[id]) {
                card.addClass('active');
            } else {
                card.removeClass('active');
            }
        });
    }

    function applyProductFilter() {
        $('.product-grid-item').each(function () {
            const item = $(this);

            const itemCategory = String(item.data('category'));
            const itemBrand = String(item.data('brand'));

            const categoryOk = selectedCategory === 'all' || itemCategory === selectedCategory;
            const brandOk = selectedBrand === 'all' || itemBrand === selectedBrand;

            item.toggle(categoryOk && brandOk);
        });
    }

    $(document).on('click', '.product-filter', function () {
        const button = $(this);
        const type = button.data('filter-type');
        const value = String(button.data('filter-value'));

        if (type === 'category') {
            selectedCategory = value;
            $('.product-filter[data-filter-type="category"]').removeClass('active');
        }

        if (type === 'brand') {
            selectedBrand = value;
            $('.product-filter[data-filter-type="brand"]').removeClass('active');
        }

        button.addClass('active');
        applyProductFilter();
    });

    $(document).on('click', '.order-product-card', function () {
        const card = $(this);
        const id = String(card.data('id'));

        if (selectedProducts[id]) {
            delete selectedProducts[id];
        } else {
            addProductFromCard(card);
        }

        syncOrderCards();
        renderSummary();
    });

    $(document).on('click', '.add-product-to-order', function () {
        const productId = String($(this).data('product-id'));
        const card = $('.order-product-card[data-id="' + productId + '"]');

        if (!card.length) {
            alert('এই প্রোডাক্টটি campaign order product হিসেবে attach করা নেই। Admin panel থেকে campaign-এর সাথে product attach করুন।');
            return;
        }

        addProductFromCard(card);
        syncOrderCards();
        renderSummary();

        $('html, body').animate({
            scrollTop: $('#order-section').offset().top - 75
        }, 500);
    });

    $(document).on('change', '.summary-qty', function () {
        const id = String($(this).data('id'));
        const qty = parseInt($(this).val(), 10) || 1;

        if (selectedProducts[id]) {
            selectedProducts[id].quantity = qty;
        }

        renderSummary();
    });

    $(document).on('click', '.remove-summary-item', function () {
        const id = String($(this).data('id'));

        delete selectedProducts[id];

        syncOrderCards();
        renderSummary();
    });

    $('#deliveryArea').on('change', function () {
        renderSummary();
    });

    $('#landingOrderForm').on('submit', function () {
        renderSummary();

        if (Object.keys(selectedProducts).length === 0) {
            alert('কমপক্ষে একটি প্রোডাক্ট নির্বাচন করুন।');
            return false;
        }

        $('#orderSubmitBtn')
            .prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm mr-2"></span> অর্ডার সাবমিট হচ্ছে...');
    });

    $('#reviewCarousel').carousel({
        interval: 2800,
        ride: 'carousel',
        pause: 'hover',
        wrap: true
    });

    applyProductFilter();
    syncOrderCards();
    renderSummary();
});
</script>
@endpush