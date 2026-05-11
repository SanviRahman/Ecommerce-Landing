@extends('frontend.layouts.master')

@php
$websiteName = $siteSetting->website_name ?? config('app.name', 'EcoEats');

$pageTitle = $campaign?->meta_title
?: $campaign?->title
?: $websiteName;

$pageDescription = $campaign?->meta_description
?: $siteSetting?->business_short_description
?: 'Premium ecommerce landing page.';

$noImage = asset('frontend/images/no-image.svg');

$imageOf = function ($model, $fallback = null) use ($noImage) {
$fallback = $fallback ?: $noImage;

if (! $model) {
return $fallback;
}

foreach ([
'image_url',
'photo_url',
'avatar_url',
'customer_image',
'customer_image_url',
'customer_photo_url',
'review_image_url',
'thumbnail',
'thumbnail_url',
'banner_image_url',
'image_one_url',
'image_two_url',
'image_three_url',
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

foreach ([
'image',
'photo',
'avatar',
'customer_photo',
'review_image',
'profile_photo',
] as $attribute) {
try {
if (! empty($model->{$attribute})) {
$value = $model->{$attribute};

if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])) {
return $value;
}

$value = ltrim($value, '/');

if (\Illuminate\Support\Str::startsWith($value, ['storage/'])) {
return asset($value);
}

if (\Illuminate\Support\Str::startsWith($value, ['public/'])) {
return \Illuminate\Support\Facades\Storage::url(str_replace('public/', '', $value));
}

return \Illuminate\Support\Facades\Storage::url($value);
}
} catch (\Throwable $e) {
//
}
}

if (method_exists($model, 'getFirstMediaUrl')) {
foreach ([
'review_customer_image',
'review_image',
'customer_image',
'customer_photo',
'avatar',
'photo',
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
'site_logo',
] as $collection) {
try {
$url = $model->getFirstMediaUrl($collection);

if (! empty($url)) {
return $url;
}
} catch (\Throwable $e) {
//
}
}
}

return $fallback;
};

$valueOf = function ($model, array $attributes, $fallback = null) {
foreach ($attributes as $attribute) {
try {
if ($model && ! empty($model->{$attribute})) {
return $model->{$attribute};
}
} catch (\Throwable $e) {
//
}
}

return $fallback;
};

$socialMedias = $socialMedias ?? collect();

try {
if ($socialMedias->isEmpty() && class_exists(\App\Models\SocialMedia::class)) {
$socialMedias = \App\Models\SocialMedia::active()->get();
}
} catch (\Throwable $e) {
$socialMedias = collect();
}

$socialLinkByPlatform = function (array $platforms) use ($socialMedias) {
foreach ($platforms as $platform) {
$platform = \Illuminate\Support\Str::lower($platform);

$social = $socialMedias->first(function ($item) use ($platform) {
$platformName = \Illuminate\Support\Str::lower($item->platform_name ?? '');
$iconClass = \Illuminate\Support\Str::lower($item->icon_class ?? '');
$link = \Illuminate\Support\Str::lower($item->link ?? '');

return \Illuminate\Support\Str::contains($platformName, $platform)
|| \Illuminate\Support\Str::contains($iconClass, $platform)
|| \Illuminate\Support\Str::contains($link, $platform);
});

if ($social && ! empty($social->link)) {
return trim($social->link);
}
}

return null;
};

$makePhoneUrl = function ($phone) {
if (! $phone) {
return null;
}

$phone = trim($phone);

if (\Illuminate\Support\Str::startsWith($phone, ['tel:'])) {
return $phone;
}

$cleanPhone = preg_replace('/[^\d+]/', '', $phone);

return $cleanPhone ? 'tel:' . $cleanPhone : null;
};

$makeWhatsappUrl = function ($value) {
if (! $value) {
return null;
}

$value = trim($value);

if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])) {
return $value;
}

if (\Illuminate\Support\Str::startsWith($value, ['//'])) {
return 'https:' . $value;
}

$number = preg_replace('/\D+/', '', $value);

if (! $number) {
return null;
}

if (\Illuminate\Support\Str::startsWith($number, '01')) {
$number = '88' . $number;
}

return 'https://wa.me/' . $number;
};

$makeMessengerUrl = function ($value) {
if (! $value) {
return null;
}

$value = trim($value);

if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])) {
return $value;
}

if (\Illuminate\Support\Str::startsWith($value, ['m.me/', 'www.m.me/'])) {
return 'https://' . $value;
}

if (\Illuminate\Support\Str::startsWith($value, ['facebook.com/', 'www.facebook.com/'])) {
return 'https://' . $value;
}

return 'https://m.me/' . ltrim($value, '@/');
};

$heroImage = $campaign
? ($campaign->banner_image_url ?: ($campaign->image_one_url ?: $noImage))
: $noImage;

$heroVideoUrl = null;

try {
if ($campaign && method_exists($campaign, 'getFirstMediaUrl')) {
$heroVideoUrl = $campaign->getFirstMediaUrl('campaign_video') ?: null;
}
} catch (\Throwable $e) {
$heroVideoUrl = null;
}

$heroVideoUrl = $heroVideoUrl ?: $valueOf($campaign, [
'campaign_video_url',
'video_url',
'video_link',
'youtube_url',
'youtube_link',
'video',
]);

$heroVideoPoster = $heroImage;
$videoEmbedUrl = null;
$videoFileUrl = null;

if ($heroVideoUrl) {
if (\Illuminate\Support\Str::contains($heroVideoUrl, ['youtube.com/watch', 'youtu.be/', 'youtube.com/shorts'])) {
preg_match('/(?:v=|youtu\.be\/|shorts\/)([^&?\/]+)/', $heroVideoUrl, $matches);
$youtubeId = $matches[1] ?? null;

$videoEmbedUrl = $youtubeId
? 'https://www.youtube.com/embed/' . $youtubeId . '?rel=0&autoplay=0'
: $heroVideoUrl;
} elseif (\Illuminate\Support\Str::contains($heroVideoUrl, ['youtube.com/embed', 'facebook.com/plugins/video'])) {
$videoEmbedUrl = $heroVideoUrl;
} else {
$videoFileUrl = \Illuminate\Support\Str::startsWith($heroVideoUrl, ['http://', 'https://', '/'])
? $heroVideoUrl
: \Illuminate\Support\Facades\Storage::url($heroVideoUrl);
}
}

$heroTitle = $campaign?->title ?: 'খুলনার বিখ্যাত চুইঝাল!';

$heroSubtitle = $campaign?->short_description
?: 'ঘরে বসেই অর্ডার করুন পছন্দের প্রিমিয়াম পণ্য। ক্যাশ অন ডেলিভারি এবং দ্রুত ডেলিভারি সুবিধা।';

$categories = $categories ?? collect();
$brands = $brands ?? collect();
$products = $products ?? collect();
$orderProducts = $orderProducts ?? collect();
$reviews = $reviews ?? collect();
$faqs = $faqs ?? collect();

$reviewItems = $reviews->values();

$phoneNumber = $valueOf($siteSetting, [
'phone',
'hotline',
'phone_number',
'mobile',
'mobile_number',
'helpline',
'contact_number',
])
?: $socialLinkByPlatform([
'phone',
'hotline',
'call',
'mobile',
'helpline',
'telephone',
'fa-phone',
'phone-alt',
]);

$whatsappNumber = $valueOf($siteSetting, [
'whatsapp_number',
'whatsapp',
'whats_app',
'whatsapp_phone',
])
?: $socialLinkByPlatform([
'whatsapp',
'whats app',
'wa.me',
'api.whatsapp',
'fa-whatsapp',
]);

$messengerLink = $valueOf($siteSetting, [
'messenger_link',
'facebook_messenger',
'messenger',
])
?: $socialLinkByPlatform([
'messenger',
'facebook messenger',
'm.me',
'facebook.com',
'fb.com',
'fa-facebook-messenger',
]);

$phoneUrl = $makePhoneUrl($phoneNumber);
$whatsappUrl = $makeWhatsappUrl($whatsappNumber);
$messengerUrl = $makeMessengerUrl($messengerLink);
@endphp

@section('title', $pageTitle)
@section('meta_description', $pageDescription)

@push('css')
<style>
:root {
    --front-green: #22c55e;
    --front-green-dark: #16a34a;
    --front-dark: #334155;
    --front-soft: #f8fafc;
    --front-border: #e5e7eb;
    --front-muted: #94a3b8;
    --front-text: #64748b;
    --front-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
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
    transition: 0.25s ease;
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
    letter-spacing: -0.5px;
}

.btn-success {
    background: var(--front-green);
    border-color: var(--front-green);
}

.btn-success:hover,
.btn-success:focus {
    background: var(--front-green-dark) !important;
    border-color: var(--front-green-dark) !important;
}

.main-sidebar .nav-sidebar .nav-link:hover,
.main-sidebar .nav-sidebar .nav-link.active {
    background: var(--front-green) !important;
    color: #ffffff !important;
}

.main-sidebar .nav-sidebar .nav-link:hover i,
.main-sidebar .nav-sidebar .nav-link.active i {
    color: #ffffff !important;
}

.navbar-nav .nav-link:hover,
.frontend-navbar .nav-link:hover,
.header-menu a:hover,
.front-header a:hover {
    color: var(--front-green) !important;
}

.hero-section {
    position: relative;
    overflow: hidden;
    padding: 74px 0 58px;
    background:
        radial-gradient(circle at 6% 16%, rgba(34, 197, 94, 0.08), transparent 26%),
        radial-gradient(circle at 88% 8%, rgba(34, 197, 94, 0.08), transparent 24%),
        #ffffff;
}

.hero-title {
    color: var(--front-green);
    font-size: 42px;
    font-weight: 900;
    margin-bottom: 22px;
    line-height: 1.25;
}

.hero-text {
    color: var(--front-text);
    font-size: 20px;
    line-height: 1.9;
}

.hero-check-list {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px 28px;
    margin: 34px 0;
}

.hero-check-item {
    color: #7c8da5;
    font-size: 17px;
    font-weight: 800;
}

.hero-check-item i {
    color: var(--front-green);
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
    font-weight: 800;
    line-height: 1.7;
}

.hero-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
}

.hero-actions .btn {
    border-radius: 9px;
    padding: 13px 24px;
    font-weight: 900;
}

.side-action-btn,
.help-icon-btn {
    width: 48px;
    height: 48px;
    border-radius: 9px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    background: #475569;
    border: 1px solid #475569;
    color: #ffffff !important;
    font-size: 19px;
    text-decoration: none !important;
    transition: 0.25s ease;
}

.side-action-btn:hover,
.side-action-btn:focus,
.help-icon-btn:hover,
.help-icon-btn:focus {
    background: var(--front-green) !important;
    border-color: var(--front-green) !important;
    color: #ffffff !important;
    transform: translateY(-2px);
}

.hero-video-box {
    position: relative;
    overflow: hidden;
    max-width: 445px;
    margin-left: auto;
    border-radius: 20px;
    background: var(--front-soft);
    box-shadow: var(--front-shadow);
}

.hero-video-box img,
.hero-video-box video,
.hero-video-box iframe {
    width: 100%;
    height: 360px;
    object-fit: cover;
    border: 0;
    display: block;
}

.category-card,
.brand-card {
    min-height: 68px;
    border: 1px solid var(--front-border);
    border-radius: 14px;
    padding: 18px 14px;
    text-align: center;
    font-weight: 900;
    color: #475569;
    background: #ffffff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.045);
    transition: 0.25s ease;
}

.category-card:hover,
.brand-card:hover {
    color: #ffffff;
    border-color: var(--front-green);
    background: var(--front-green);
    transform: translateY(-3px);
}

.category-card i,
.brand-card i {
    color: var(--front-green);
    margin-right: 7px;
    transition: 0.25s ease;
}

.category-card:hover i,
.brand-card:hover i {
    color: #ffffff;
}

.filter-card {
    background: #ffffff;
    border: 1px solid var(--front-border);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.045);
}

.filter-title {
    font-weight: 900;
    color: var(--front-dark);
    font-size: 18px;
    margin-bottom: 12px;
}

.filter-chip {
    border: 1px solid #dbe4ef;
    background: #ffffff;
    color: #64748b;
    border-radius: 999px;
    padding: 8px 14px;
    font-weight: 800;
    font-size: 14px;
    margin: 0 8px 10px 0;
    cursor: pointer;
    transition: 0.2s ease;
}

.filter-chip.active,
.filter-chip:hover {
    background: var(--front-green);
    border-color: var(--front-green);
    color: #ffffff;
}

.product-card {
    height: 100%;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid var(--front-border);
    border-radius: 18px;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.055);
    transition: 0.25s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 22px 55px rgba(15, 23, 42, 0.10);
    border-color: rgba(34, 197, 94, 0.35);
}

.product-image {
    width: 100%;
    height: 220px;
    object-fit: cover;
    background: var(--front-soft);
}

.product-body {
    padding: 18px;
}

.product-body h4 {
    color: var(--front-dark);
    font-size: 19px;
    font-weight: 900;
    margin-bottom: 8px;
}

.product-meta {
    color: var(--front-muted);
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
    color: var(--front-muted);
    font-size: 14px;
    margin-left: 8px;
    font-weight: 500;
}

.add-order-btn {
    border-radius: 9px;
    font-weight: 900;
}

.free-delivery-btn {
    background: #16a34a !important;
    border-color: #16a34a !important;
}

.difference-section {
    background: linear-gradient(180deg, #ffffff, #f8fafc);
}

.difference-table {
    color: var(--front-text);
    font-size: 16px;
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.045);
}

.difference-table td {
    border-color: #edf2f7;
    padding: 14px 16px;
    text-align: center;
}

.side-product-img {
    width: 100%;
    height: 275px;
    object-fit: cover;
    border-radius: 18px;
    box-shadow: var(--front-shadow);
}

.service-card {
    height: 100%;
    text-align: center;
    padding: 28px 17px;
    border-radius: 18px;
    transition: 0.25s ease;
}

.service-card:hover {
    background: #ffffff;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.07);
    transform: translateY(-4px);
}

.service-icon {
    width: 82px;
    height: 82px;
    border-radius: 18px;
    background: #f0fdf4;
    color: var(--front-green);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 22px;
}

.service-card h4 {
    color: var(--front-dark);
    font-size: 20px;
    font-weight: 900;
}

.service-card p {
    color: var(--front-muted);
    font-size: 16px;
    line-height: 1.7;
}

.wide-banner {
    position: relative;
    overflow: hidden;
    height: 265px;
    border-radius: 18px;
    background: var(--front-soft);
    box-shadow: var(--front-shadow);
}

.wide-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: 0.35s ease;
}

.wide-banner:hover img {
    transform: scale(1.035);
}

.review-carousel-wrapper {
    position: relative;
    max-width: 850px;
    margin: 0 auto;
    padding: 0 52px 48px;
    overflow: hidden;
}

.review-single-wrap {
    max-width: 720px;
    margin: 0 auto;
}

.review-card {
    min-height: 296px;
    height: 100%;
    padding: 38px 38px 34px;
    border: 1px solid #eef2f7;
    border-radius: 20px;
    background: linear-gradient(145deg, #ffffff, #f8fafc);
    box-shadow: var(--front-shadow);
    transition: 0.3s ease;
}

.review-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
    border-color: rgba(34, 197, 94, 0.35);
}

.review-card .stars {
    color: #f59e0b;
    margin-bottom: 20px;
    letter-spacing: 2px;
    font-size: 17px;
}

.review-card p {
    color: var(--front-text);
    line-height: 1.9;
    font-size: 17px;
    min-height: 88px;
    margin-bottom: 0;
}

.review-user {
    display: flex;
    align-items: center;
    margin-top: 28px;
}

.review-user img {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 16px;
    background: #e2e8f0;
    border: 3px solid #ffffff;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.12);
}

.review-user strong {
    display: block;
    color: var(--front-dark);
    font-size: 17px;
    font-weight: 900;
}

.review-user span {
    color: var(--front-muted);
    font-size: 15px;
}

.review-social {
    margin-left: auto;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #eef2f7;
    color: var(--front-muted);
    font-size: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
    transition: 0.25s ease;
}

.review-social:hover {
    background: var(--front-green);
    color: #ffffff;
    transform: translateY(-2px);
}

.review-dots {
    position: static;
    justify-content: center;
    margin-top: 24px;
    margin-bottom: 0;
}

.review-dots li {
    width: 10px;
    height: 10px;
    border: 0;
    border-radius: 50%;
    background: #cbd5e1;
    opacity: 1;
    margin: 0 6px;
    transition: 0.25s ease;
}

.review-dots li.active {
    width: 24px;
    border-radius: 999px;
    background: var(--front-green);
}

.review-control {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #e9eef5;
    top: 43%;
    opacity: 1;
    transition: 0.25s ease;
}

.review-control:hover,
.review-control:focus {
    background: var(--front-green);
    opacity: 1;
    transform: scale(1.06);
}

.faq-wrapper {
    max-width: 920px;
    margin: auto;
    background: #ffffff;
    border: 1px solid #eef2f7;
    border-radius: 18px;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.055);
    overflow: hidden;
}

.faq-item {
    border-top: 1px solid #eef2f7;
}

.faq-item:first-child {
    border-top: 0;
}

.faq-button {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: transparent;
    border: 0;
    padding: 24px 26px;
    text-align: left;
    color: var(--front-dark);
    font-size: 19px;
    font-weight: 900;
}

.faq-button:hover {
    color: var(--front-green);
}

.faq-body {
    color: var(--front-text);
    line-height: 1.8;
    padding: 0 26px 24px;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}

.gallery-grid img {
    width: 100%;
    height: 225px;
    border-radius: 18px;
    object-fit: cover;
    background: var(--front-soft);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    transition: 0.25s ease;
}

.gallery-grid img:hover {
    transform: translateY(-4px) scale(1.015);
    box-shadow: 0 22px 55px rgba(15, 23, 42, 0.10);
}

.order-section {
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    padding: 82px 0;
}

.order-product-card {
    min-height: 96px;
    display: flex;
    align-items: center;
    position: relative;
    cursor: pointer;
    padding: 12px;
    border: 1px solid var(--front-border);
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.035);
    transition: 0.2s ease;
}

.order-product-card:hover {
    border-color: rgba(34, 197, 94, 0.4);
    transform: translateY(-2px);
}

.order-product-card.active {
    border-color: var(--front-green);
    box-shadow: 0 0 0 1px var(--front-green), 0 16px 38px rgba(34, 197, 94, 0.12);
    background: #f0fdf4;
}

.order-product-card img {
    width: 78px;
    height: 68px;
    object-fit: cover;
    border-radius: 10px;
    margin-right: 12px;
    background: #e2e8f0;
}

.order-product-card h5 {
    color: var(--front-dark);
    font-size: 17px;
    font-weight: 900;
    margin-bottom: 5px;
}

.order-product-card p {
    margin: 0;
    color: var(--front-text);
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
    color: var(--front-dark);
    font-size: 24px;
    font-weight: 900;
    margin-bottom: 25px;
}

.delivery-area-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.delivery-area-card {
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 14px 16px;
    cursor: pointer;
    background: #ffffff;
    transition: 0.25s ease;
}

.delivery-area-card input {
    display: none;
}

.delivery-area-card.active,
.delivery-area-card:hover {
    border-color: var(--front-green);
    background: #f0fdf4;
    box-shadow: 0 8px 22px rgba(34, 197, 94, 0.12);
}

.delivery-area-title {
    display: block;
    font-weight: 900;
    color: #334155;
}

.delivery-area-charge {
    display: block;
    margin-top: 4px;
    color: var(--front-green-dark);
    font-weight: 900;
}

.delivery-area-free-box {
    border: 1px solid var(--front-green);
    background: #f0fdf4;
    color: #16a34a;
    border-radius: 12px;
    padding: 16px;
    font-weight: 900;
    font-size: 17px;
    box-shadow: 0 8px 22px rgba(34, 197, 94, 0.12);
}

@media (max-width: 767px) {
    .delivery-area-options {
        grid-template-columns: 1fr;
    }
}
.summary-line,
.summary-product {
    border-top: 1px dashed #cbd5e1;
    padding: 16px 0;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    color: var(--front-muted);
    font-size: 16px;
}

.summary-line strong {
    color: var(--front-green-dark);
}

.summary-product {
    display: flex;
    align-items: center;
    justify-content: space-between;
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
    border-radius: 10px;
    margin-right: 12px;
    background: #e2e8f0;
}

.summary-product-info h5 {
    color: var(--front-green-dark);
    font-size: 17px;
    font-weight: 900;
    margin-bottom: 4px;
}

.summary-product-info span {
    color: var(--front-muted);
    font-size: 14px;
}

.summary-qty {
    width: 76px;
    height: 42px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 5px;
}

.remove-summary-item {
    color: var(--front-muted);
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
    color: var(--front-muted);
    font-weight: 800;
    font-size: 15px;
}

.checkout-form .form-control {
    min-height: 48px;
    border-color: #cbd5e1;
    border-radius: 10px;
}

.order-submit-btn {
    min-height: 58px;
    border-radius: 12px;
    font-size: 18px;
    font-weight: 900;
    background: var(--front-green);
    border-color: var(--front-green);
}

.help-cta-section {
    padding: 30px 0 76px;
    background: #ffffff;
}

.help-box {
    position: relative;
    overflow: hidden;
    min-height: 310px;
    padding: 66px 20px 60px;
    text-align: center;
    border-radius: 20px;
    border: 1px solid #eef2f7;
    box-shadow: var(--front-shadow);
    background:
        linear-gradient(rgba(255, 255, 255, 0.80), rgba(255, 255, 255, 0.80)),
        radial-gradient(ellipse at 9% 8%, rgba(34, 197, 94, 0.18) 0, rgba(34, 197, 94, 0.13) 95px, transparent 98px),
        radial-gradient(ellipse at 20% 52%, rgba(34, 197, 94, 0.10) 0, rgba(34, 197, 94, 0.08) 120px, transparent 124px),
        radial-gradient(ellipse at 88% 18%, rgba(34, 197, 94, 0.16) 0, rgba(34, 197, 94, 0.12) 110px, transparent 114px),
        radial-gradient(ellipse at 82% 78%, rgba(34, 197, 94, 0.12) 0, rgba(34, 197, 94, 0.09) 100px, transparent 104px),
        #f7fbf7;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.help-title {
    color: var(--front-dark);
    font-size: 32px;
    font-weight: 900;
    margin-bottom: 18px;
}

.help-text {
    max-width: 780px;
    margin: 0 auto 28px;
    color: var(--front-muted);
    font-size: 16px;
    line-height: 1.85;
    font-weight: 500;
}

.help-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
}

.help-main-btn {
    min-width: 130px;
    height: 48px;
    padding: 0 24px;
    border-radius: 9px;
    background: var(--front-green);
    border: 1px solid var(--front-green);
    color: #ffffff !important;
    font-size: 16px;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
    transition: 0.25s ease;
}

.help-main-btn i {
    margin-right: 8px;
}

.help-main-btn:hover,
.help-main-btn:focus {
    background: var(--front-green-dark);
    border-color: var(--front-green-dark);
    color: #ffffff !important;
    transform: translateY(-2px);
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
        border-radius: 20px;
    }

    .hero-video-box img,
    .hero-video-box video,
    .hero-video-box iframe {
        height: 490px;
        border-radius: 20px;
    }

    .section-space {
        padding: 58px 0;
    }

    .hero-check-list {
        grid-template-columns: repeat(2, 1fr);
    }

    .gallery-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .review-control {
        display: none;
    }
}

@media (max-width: 767px) {
    .section-title {
        font-size: 28px;
    }

    .review-carousel-wrapper {
        padding: 0 0 40px;
    }

    .review-card {
        padding: 30px 24px;
        min-height: 285px;
    }

    .review-card p {
        font-size: 15px;
        min-height: 105px;
    }

    .review-user img {
        width: 52px;
        height: 52px;
    }

    .delivery-area-options {
        grid-template-columns: 1fr;
    }

    .help-cta-section {
        padding: 20px 0 58px;
    }

    .help-box {
        min-height: 270px;
        padding: 45px 16px;
    }

    .help-title {
        font-size: 27px;
    }

    .help-text {
        font-size: 15px;
        line-height: 1.75;
    }

    .help-main-btn {
        height: 46px;
        padding: 0 20px;
        font-size: 15px;
    }

    .help-icon-btn,
    .side-action-btn {
        width: 46px;
        height: 46px;
    }
}

@media (max-width: 575px) {
    .container {
        padding-left: 16px;
        padding-right: 16px;
    }

    .hero-video-box img,
    .hero-video-box video,
    .hero-video-box iframe {
        height: 490px;
    }

    .gallery-grid {
        grid-template-columns: 1fr;
    }

    .gallery-grid img {
        height: 250px;
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

@if (session('error'))
<div class="container mt-3">
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>Error!</strong> {{ session('error') }}
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

{{-- Hero --}}
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
                    <a href="#order-section" class="btn btn-success">
                        <i class="fas fa-shopping-cart mr-2"></i>
                        অর্ডার করুন
                    </a>

                    @if($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" target="_blank" class="btn side-action-btn" aria-label="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    @endif

                    @if($phoneUrl)
                    <a href="{{ $phoneUrl }}" class="btn side-action-btn" aria-label="Phone">
                        <i class="fas fa-phone-alt"></i>
                    </a>
                    @endif
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hero-video-box">
                    @if($videoEmbedUrl)
                    <iframe src="{{ $videoEmbedUrl }}" title="{{ $heroTitle }}" allowfullscreen></iframe>
                    @elseif($videoFileUrl)
                    <video controls preload="metadata" poster="{{ $heroVideoPoster }}">
                        <source src="{{ $videoFileUrl }}" type="video/mp4">
                    </video>
                    @elseif($heroVideoUrl)
                    <a href="{{ $heroVideoUrl }}" target="_blank" class="hero-video-link">
                        <img src="{{ $heroVideoPoster }}" alt="{{ $heroTitle }}"
                            onerror="this.onerror=null;this.src='{{ $noImage }}';">
                    </a>
                    @else
                    <img src="{{ $heroVideoPoster }}" alt="{{ $heroTitle }}"
                        onerror="this.onerror=null;this.src='{{ $noImage }}';">
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Category --}}
<section class="section-space" id="category-section">
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

{{-- Brand --}}
<section class="pb-5" id="brand-section">
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

{{-- Products --}}
<section class="section-space bg-white" id="products-section">
    <div class="container">
        <h2 class="section-title">আমাদের প্রোডাক্ট</h2>

        <div class="filter-card mb-4">
            <div class="row">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="filter-title">ক্যাটাগরি দিয়ে ফিল্টার</div>

                    <button type="button" class="filter-chip active product-filter" data-filter-type="category"
                        data-filter-value="all">
                        All Categories
                    </button>

                    @foreach($categories as $category)
                    <button type="button" class="filter-chip product-filter" data-filter-type="category"
                        data-filter-value="{{ $category->id }}">
                        {{ $category->name }}
                    </button>
                    @endforeach
                </div>

                <div class="col-lg-6">
                    <div class="filter-title">ব্র্যান্ড দিয়ে ফিল্টার</div>

                    <button type="button" class="filter-chip active product-filter" data-filter-type="brand"
                        data-filter-value="all">
                        All Brands
                    </button>

                    @foreach($brands as $brand)
                    <button type="button" class="filter-chip product-filter" data-filter-type="brand"
                        data-filter-value="{{ $brand->id }}">
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

            <div class="col-lg-3 col-md-4 col-sm-6 mb-4 product-grid-item" data-category="{{ $product->category_id }}"
                data-brand="{{ $product->brand_id ?: 'none' }}">
                <div class="product-card">
                    <img src="{{ $productImage }}" class="product-image" alt="{{ $product->name }}"
                        onerror="this.onerror=null;this.src='{{ $noImage }}';">

                    <div class="product-body">
                        <h4>{{ \Illuminate\Support\Str::limit($product->name, 35) }}</h4>

                        <div class="product-meta">
                            {{ $productWeight }}

                            @if($product->is_free_delivery)
                            <span class="badge badge-success ml-1">ফ্রি ডেলিভারি</span>
                            @endif
                        </div>

                        <div class="product-price">
                            ৳{{ number_format($productPrice) }}

                            @if($productOldPrice)
                            <del>৳{{ number_format($productOldPrice) }}</del>
                            @endif
                        </div>

                        <button type="button"
                            class="btn btn-success btn-block add-order-btn add-product-to-order {{ $product->is_free_delivery ? 'free-delivery-btn' : '' }}"
                            data-product-id="{{ $product->id }}">
                            <i class="fas fa-cart-plus mr-1"></i>
                            {{ $product->is_free_delivery ? 'ফ্রি ডেলিভারিতে অর্ডার করুন' : 'অর্ডারে যোগ করুন' }}
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="alert alert-warning text-center">কোনো active product পাওয়া যায়নি।</div>
            </div>
            @endforelse
        </div>
    </div>
</section>

{{-- Difference --}}
<section class="section-space difference-section" id="difference-section">
    <div class="container">
        <h2 class="section-title">চুইঝালের পার্থক্যসমূহ</h2>

        <div class="row align-items-center">
            <div class="col-lg-3 mb-4 mb-lg-0">
                <img src="{{ $campaign ? ($campaign->image_one_url ?: $heroImage) : $heroImage }}"
                    class="side-product-img" alt="Product" onerror="this.onerror=null;this.src='{{ $noImage }}';">
            </div>

            <div class="col-lg-6">
                <h4 class="text-center font-weight-bold mb-4">গাছ চুইঝাল &nbsp;&nbsp; এটা চুইঝাল</h4>

                <table class="table difference-table mb-0">
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

            <div class="col-lg-3 mt-4 mt-lg-0">
                <img src="{{ $campaign ? ($campaign->image_two_url ?: $heroImage) : $heroImage }}"
                    class="side-product-img" alt="Product" onerror="this.onerror=null;this.src='{{ $noImage }}';">
            </div>
        </div>
    </div>
</section>

{{-- Service --}}
<section class="section-space" id="services-section">
    <div class="container">
        <h2 class="section-title">কেন আমরাই সেরা</h2>

        <div class="row">
            <div class="col-md-3 col-6 mb-3">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-award"></i></div>
                    <h4>অর্গানিক প্রোডাক্ট</h4>
                    <p>আমাদের কাছে পাবেন সেরা মানের প্রিমিয়াম পণ্য।</p>
                </div>
            </div>

            <div class="col-md-3 col-6 mb-3">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-crown"></i></div>
                    <h4>প্রিমিয়াম কোয়ালিটি</h4>
                    <p>সেরা কোয়ালিটির পণ্য সংগ্রহ করে সরবরাহ করা হয়।</p>
                </div>
            </div>

            <div class="col-md-3 col-6 mb-3">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-undo-alt"></i></div>
                    <h4>রিটার্ন পলিসি</h4>
                    <p>সমস্যা হলে সহজ রিটার্ন ও রিপ্লেসমেন্ট সুবিধা।</p>
                </div>
            </div>

            <div class="col-md-3 col-6 mb-3">
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
            <img src="{{ $campaign ? ($campaign->banner_image_url ?: $heroImage) : $heroImage }}"
                alt="{{ $campaign?->title ?? 'Banner' }}" onerror="this.onerror=null;this.src='{{ $noImage }}';">
        </div>
    </div>
</section>

{{-- Review --}}
<section class="section-space" id="reviews-section">
    <div class="container">
        <h2 class="section-title">কাস্টমার রিভিউ</h2>

        <div class="review-carousel-wrapper">
            @if($reviewItems->isNotEmpty())
            <div id="reviewCarousel" class="carousel slide review-one-carousel" data-ride="carousel"
                data-interval="3000" data-pause="hover" data-wrap="true">

                <div class="carousel-inner">
                    @foreach($reviewItems as $review)
                    @php
                    $rating = (int) ($review->rating ?: 5);
                    $rating = $rating < 1 ? 5 : ($rating> 5 ? 5 : $rating);
                        $reviewImage = $imageOf($review, $noImage);
                        @endphp

                        <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                            <div class="review-single-wrap">
                                <div class="review-card">
                                    <div class="stars">
                                        @for($i = 1; $i <= 5; $i++) <i
                                            class="{{ $i <= $rating ? 'fas' : 'far' }} fa-star"></i>
                                            @endfor
                                    </div>

                                    <p>
                                        {{ $review->review_text ?: 'প্রোডাক্টের কোয়ালিটি ভালো ছিল এবং ডেলিভারিও দ্রুত হয়েছে। ধন্যবাদ।' }}
                                    </p>

                                    <div class="review-user">
                                        <img src="{{ $reviewImage }}" alt="{{ $review->customer_name ?? 'Customer' }}"
                                            onerror="this.onerror=null;this.src='{{ $noImage }}';">

                                        <div>
                                            <strong>{{ $review->customer_name ?? 'Customer' }}</strong>
                                            <span>{{ $review->location ?: 'ঢাকা' }}</span>
                                        </div>

                                        @if($review->social_link)
                                        <a href="{{ $review->social_link }}" target="_blank" class="review-social"
                                            aria-label="Facebook">
                                            <i class="fab fa-facebook-f"></i>
                                        </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                </div>

                @if($reviewItems->count() > 1)
                <a class="carousel-control-prev review-control" href="#reviewCarousel" role="button" data-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </a>

                <a class="carousel-control-next review-control" href="#reviewCarousel" role="button" data-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </a>

                <ol class="carousel-indicators review-dots">
                    @foreach($reviewItems as $review)
                    <li data-target="#reviewCarousel" data-slide-to="{{ $loop->index }}"
                        class="{{ $loop->first ? 'active' : '' }}">
                    </li>
                    @endforeach
                </ol>
                @endif
            </div>
            @else
            <div class="alert alert-warning text-center">
                কোনো review পাওয়া যায়নি।
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
            <div class="faq-item">
                <button class="faq-button" type="button">
                    <span>FAQ পাওয়া যায়নি।</span>
                </button>
            </div>
            @endforelse
        </div>
    </div>
</section>

{{-- Gallery --}}
<section class="section-space" id="gallery-section">
    <div class="container">
        <h2 class="section-title">প্রোডাক্ট গ্যালারি</h2>

        <div class="gallery-grid">
            @forelse($products as $product)
            <img src="{{ $imageOf($product) }}" alt="{{ $product->name }}" title="{{ $product->name }}"
                onerror="this.onerror=null;this.src='{{ $noImage }}';">
            @empty
            <div class="alert alert-warning text-center w-100">
                কোনো product gallery image পাওয়া যায়নি।
            </div>
            @endforelse
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
                        $campaignPrice = (int) optional($product->pivot)->campaign_price;
                        $unitPrice = $campaignPrice > 0 ? $campaignPrice : (int) $product->new_price;
                        $weight = $product->weight_size ?: '৫০০ গ্রাম';
                        $productImage = $imageOf($product);
                        @endphp

                        <div class="col-md-6 mb-3">
                            <div class="order-product-card {{ $loop->first ? 'active' : '' }}"
                                data-id="{{ $product->id }}" data-name="{{ e($product->name) }}"
                                data-price="{{ $unitPrice }}" data-weight="{{ e($weight) }}"
                                data-image="{{ e($productImage) }}"
                                data-free-delivery="{{ $product->is_free_delivery ? 1 : 0 }}">
                                <span class="selected-check">
                                    <i class="fas fa-check"></i>
                                </span>

                                <img src="{{ $productImage }}" alt="{{ $product->name }}"
                                    onerror="this.onerror=null;this.src='{{ $noImage }}';">

                                <div>
                                    <h5>{{ \Illuminate\Support\Str::limit($product->name, 22) }}</h5>
                                    <p>
                                        ৳ {{ number_format($unitPrice) }}
                                        <span class="float-right ml-2">{{ $weight }}</span>

                                        @if($product->is_free_delivery)
                                        <br>
                                        <span class="badge badge-success mt-1">
                                            ফ্রি ডেলিভারি
                                        </span>
                                        @endif
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
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}"
                                class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>মোবাইল নাম্বার</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>আপনার ঠিকানা</label>
                            <textarea name="address" rows="3" class="form-control"
                                required>{{ old('address') }}</textarea>
                        </div>

                        <div class="form-group" id="deliveryAreaWrapper">
                            <label>ডেলিভারি এরিয়া</label>

                            <div class="delivery-area-free-box d-none" id="freeDeliveryAreaBox">
                                <i class="fas fa-truck mr-1"></i>
                                ফ্রি ডেলিভারি
                            </div>

                            <div class="delivery-area-options" id="deliveryAreaOptions">
                                <label class="delivery-area-card active">
                                    <input type="radio" name="delivery_area" value="inside_dhaka" checked>
                                    <span class="delivery-area-title">ঢাকার ভিতরে</span>
                                    <span class="delivery-area-charge">৳৭০</span>
                                </label>

                                <label class="delivery-area-card">
                                    <input type="radio" name="delivery_area" value="outside_dhaka">
                                    <span class="delivery-area-title">ঢাকার বাইরে</span>
                                    <span class="delivery-area-charge">৳১৩০</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>নোট / কালার / সাইজ</label>
                            <textarea name="customer_note" rows="2"
                                class="form-control">{{ old('customer_note') }}</textarea>
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
            কোনো active campaign/product পাওয়া যায়নি। আগে admin panel থেকে active campaign এবং active product তৈরি করুন।
        </div>
        @endif
    </div>
</section>

{{-- Help CTA --}}
<section class="help-cta-section">
    <div class="container">
        <div class="help-box">
            <h2 class="help-title">সাহায্য প্রয়োজন?</h2>

            <p class="help-text">
                যেকোনো জিজ্ঞাসা ও অর্ডারজনিত সমস্যায় কল করুন আমাদের হেল্পলাইনে অথবা নক করুন আমাদের হোয়াটসঅ্যাপ বা ফেসবুক
                পেজে।
                আমরা আছি সকাল ১০ টা থেকে রাত ৮ টা পর্যন্ত আপনার সেবায়।
            </p>

            <div class="help-actions">
                @if($phoneUrl)
                <a href="{{ $phoneUrl }}" class="help-main-btn">
                    <i class="fas fa-phone-alt"></i>
                    হেল্পলাইন
                </a>
                @endif

                @if($whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" class="help-icon-btn" aria-label="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
                @endif

                @if($messengerUrl)
                <a href="{{ $messengerUrl }}" target="_blank" class="help-icon-btn" aria-label="Messenger">
                    <i class="fab fa-facebook-messenger"></i>
                </a>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
$(document).ready(function () {
    const noImage = @json($noImage);

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
            image: card.data('image') || noImage,
            isFreeDelivery: Number(card.data('free-delivery') || 0) === 1,
            quantity: selectedProducts[id] ? selectedProducts[id].quantity : 1
        };
    }

    $('.order-product-card.active').each(function () {
        addProductFromCard($(this));
    });

    function updateDeliveryAreaUI(allFreeDelivery) {
        if (allFreeDelivery) {
            $('#deliveryAreaOptions').addClass('d-none');
            $('#freeDeliveryAreaBox').removeClass('d-none');

            /*
            |--------------------------------------------------------------------------
            | Backend validation needs delivery_area value.
            | Free delivery holeo hidden selected value inside_dhaka thakbe.
            |--------------------------------------------------------------------------
            */
            $('input[name="delivery_area"][value="inside_dhaka"]').prop('checked', true);
            $('.delivery-area-card').removeClass('active');
            $('input[name="delivery_area"][value="inside_dhaka"]').closest('.delivery-area-card').addClass('active');
        } else {
            $('#deliveryAreaOptions').removeClass('d-none');
            $('#freeDeliveryAreaBox').addClass('d-none');
        }
    }

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
                        <img src="${item.image}" alt="${item.name}" onerror="this.onerror=null;this.src='${noImage}';">
                        <div>
                            <h5>${item.name}</h5>
                            <span>${item.weight}</span><br>
                            <strong>৳ ${Number(item.price).toLocaleString('en-US')}</strong>
                            ${item.isFreeDelivery ? '<br><span class="badge badge-success mt-1">ফ্রি ডেলিভারি</span>' : ''}
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

        const allItems = Object.values(selectedProducts);

        const allFreeDelivery = allItems.length > 0 && allItems.every(function (item) {
            return item.isFreeDelivery === true;
        });

        updateDeliveryAreaUI(allFreeDelivery);

        const deliveryArea = $('input[name="delivery_area"]:checked').val();
        const deliveryCharge = allFreeDelivery ? 0 : (deliveryCharges[deliveryArea] || 0);
        const codCharge = 0;
        const grandTotal = subTotal + deliveryCharge + codCharge;

        $('#orderSummaryItems').html(itemsHtml);
        $('#selectedProductsInputs').html(inputsHtml);

        $('#summarySubTotal').text(money(subTotal));
        $('#summaryDeliveryCharge').text(allFreeDelivery ? 'ফ্রি ডেলিভারি' : money(deliveryCharge));
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

        if (! card.length) {
            alert('এই প্রোডাক্টটি order section-এ পাওয়া যায়নি। Page reload করে আবার চেষ্টা করুন।');
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

    $(document).on('change', 'input[name="delivery_area"]', function () {
        $('.delivery-area-card').removeClass('active');
        $(this).closest('.delivery-area-card').addClass('active');
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
        interval: 3000,
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