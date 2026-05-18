@extends('frontend.layouts.master')

@php
    $websiteName = $siteSetting->website_name ?? config('app.name', 'Laravel');

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
            'featured_image_url',
            'primary_image_url',
            'main_image_url',
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
            'thumbnail',
            'featured_image',
            'main_image',
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
                'gallery',
                'thumbnail',
                'main_image',
                'featured_image',
                'banner_image',
                'image_one',
                'image_two',
                'image_three',
                'site_logo',
                'default',
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

        try {
            if (method_exists($model, 'getMedia')) {
                foreach ([
                    'product_images',
                    'product_gallery',
                    'gallery',
                    'images',
                    'image',
                    'product_image',
                    'thumbnail',
                    'main_image',
                    'featured_image',
                    'default',
                ] as $collection) {
                    $media = $model->getMedia($collection)->first();

                    if ($media) {
                        return $media->getUrl();
                    }
                }
            }
        } catch (\Throwable $e) {
            //
        }

        return $fallback;
    };

    $galleryImageOf = function ($model, $fallback = null) use ($imageOf, $noImage) {
        $fallback = $fallback ?: $noImage;

        if (! $model) {
            return $fallback;
        }

        if (method_exists($model, 'getFirstMediaUrl')) {
            foreach ([
                'product_gallery',
                'product_images',
                'gallery',
                'images',
                'image',
                'product_image',
                'thumbnail',
                'main_image',
                'featured_image',
                'default',
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

        return $imageOf($model, $fallback);
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

        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($phone, ['tel:'])) {
            return $phone;
        }

        if (\Illuminate\Support\Str::startsWith($phone, ['http://', 'https://'])) {
            return $phone;
        }

        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);

        return $cleanPhone ? 'tel:' . $cleanPhone : null;
    };

    $makeWhatsappUrl = function ($value) {
        if (! $value) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

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

        if (\Illuminate\Support\Str::startsWith($number, '1') && strlen($number) === 10) {
            $number = '880' . $number;
        }

        return 'https://wa.me/' . $number;
    };

    $makeMessengerUrl = function ($value) {
        if (! $value) {
            return null;
        }

        $value = trim((string) $value);

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

    $categories = $categories ?? collect();
    $brands = $brands ?? collect();
    $products = $products ?? collect();
    $orderProducts = $orderProducts ?? collect();
    $reviews = $reviews ?? collect();
    $faqs = $faqs ?? collect();

    $defaultCategoryId = $categories->first()?->id;
    $defaultBrandId = 'all';

    $heroImage = $campaign
        ? ($campaign->image_three_url ?: ($campaign->banner_image_url ?: $noImage))
        : $noImage;

    $heroVideoUrl = $campaign?->embed_video_url ?: null;

    try {
        if (! $heroVideoUrl && $campaign && method_exists($campaign, 'getFirstMediaUrl')) {
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

    $youtubeEmbedFromUrl = function (?string $url): ?string {
        if (! $url) {
            return null;
        }

        $url = trim($url);
        $videoId = null;
        $start = 0;

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');
        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (isset($query['t'])) {
            $timeValue = (string) $query['t'];

            if (preg_match('/^(\d+)$/', $timeValue, $timeMatches)) {
                $start = (int) $timeMatches[1];
            } elseif (preg_match('/(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/', $timeValue, $timeMatches)) {
                $start = ((int) ($timeMatches[1] ?? 0) * 3600)
                    + ((int) ($timeMatches[2] ?? 0) * 60)
                    + ((int) ($timeMatches[3] ?? 0));
            }
        }

        if (isset($query['start'])) {
            $start = (int) $query['start'];
        }

        if (str_contains($host, 'youtu.be')) {
            $videoId = explode('/', $path)[0] ?? null;
        } elseif (str_contains($host, 'youtube.com') || str_contains($host, 'youtube-nocookie.com')) {
            if (isset($query['v'])) {
                $videoId = $query['v'];
            } elseif (preg_match('/(?:shorts|embed|live)\/([^\/?]+)/', $path, $matches)) {
                $videoId = $matches[1] ?? null;
            }
        }

        if (! $videoId && preg_match('/(?:v=|youtu\.be\/|shorts\/|embed\/)([A-Za-z0-9_-]{6,})/', $url, $matches)) {
            $videoId = $matches[1] ?? null;
        }

        if (! $videoId) {
            return null;
        }

        $videoId = preg_replace('/[^A-Za-z0-9_-]/', '', $videoId);

        if (! $videoId) {
            return null;
        }

        $params = [
            'rel' => 0,
            'autoplay' => 0,
            'modestbranding' => 1,
            'playsinline' => 1,
        ];

        if ($start > 0) {
            $params['start'] = $start;
        }

        return 'https://www.youtube-nocookie.com/embed/' . $videoId . '?' . http_build_query($params);
    };

    if ($heroVideoUrl) {
        if (\Illuminate\Support\Str::contains($heroVideoUrl, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'])) {
            $videoEmbedUrl = $youtubeEmbedFromUrl($heroVideoUrl);
        } elseif (\Illuminate\Support\Str::contains($heroVideoUrl, ['facebook.com/plugins/video'])) {
            $videoEmbedUrl = $heroVideoUrl;
        } elseif (\Illuminate\Support\Str::contains($heroVideoUrl, ['facebook.com', 'fb.watch'])) {
            $videoEmbedUrl = 'https://www.facebook.com/plugins/video.php?href=' . urlencode($heroVideoUrl) . '&show_text=false&width=560';
        } else {
            $videoFileUrl = \Illuminate\Support\Str::startsWith($heroVideoUrl, ['http://', 'https://', '/'])
                ? $heroVideoUrl
                : \Illuminate\Support\Facades\Storage::url($heroVideoUrl);
        }
    }

    $heroTitle = $campaign?->title ?: 'খুলনার বিখ্যাত চুইঝাল!';

    $heroSubtitle = $campaign?->short_description
        ?: 'ঘরে বসেই অর্ডার করুন পছন্দের প্রিমিয়াম পণ্য। ক্যাশ অন ডেলিভারি এবং দ্রুত ডেলিভারি সুবিধা।';

    $defaultBenefits = [
        'গাছের মাছ',
        'হাঁসের মাংস',
        'মাছ',
        'দেশী',
        'খাসির মাংস',
        'মুরগী ঘন্ট',
        'সরিষা',
        'কালিজিরা',
        'চটপটি',
    ];

    $benefits = collect($campaign?->benefits_text ?: $defaultBenefits)
        ->filter()
        ->values();

    $sectionTitles = $campaign?->section_titles ?? [];

    $categorySectionTitle = $sectionTitles['category_title'] ?? 'ক্যাটাগরি সমূহ';
    $brandSectionTitle = $sectionTitles['brand_title'] ?? 'ব্র্যান্ড সমূহ';
    $productSectionTitle = $sectionTitles['product_title'] ?? 'আমাদের প্রোডাক্ট';
    $categoryFilterTitle = $sectionTitles['category_filter_title'] ?? 'ক্যাটাগরি দিয়ে ফিল্টার';
    $brandFilterTitle = $sectionTitles['brand_filter_title'] ?? 'ব্র্যান্ড দিয়ে ফিল্টার';
    $comparisonSectionTitle = $sectionTitles['comparison_title'] ?? 'চুইঝালের পার্থক্যসমূহ';
    $serviceSectionTitle = $sectionTitles['service_title'] ?? 'কেন আমরাই সেরা';
    $reviewSectionTitle = $sectionTitles['review_title'] ?? 'কাস্টমার রিভিউ';
    $faqSectionTitle = $sectionTitles['faq_title'] ?? 'সচরাচর জিজ্ঞাস্য প্রশ্নাবলি';
    $gallerySectionTitle = $sectionTitles['gallery_title'] ?? 'প্রোডাক্ট গ্যালারি';
    $orderSectionTitle = $sectionTitles['order_title'] ?? ($campaign?->order_form_title ?: 'অর্ডার করুন এখনই');

    $comparisonLeftTitle = $campaign?->comparison_text['left_title'] ?? 'গাছ চুইঝাল';
    $comparisonRightTitle = $campaign?->comparison_text['right_title'] ?? 'এটা চুইঝাল';

    $comparisonLeft = $campaign?->comparison_text['left'] ?? [
        'চুইঝাল গাছের কাঠকে গাছ চুইঝাল বলা হয়।',
        'গাছ চুইঝাল সাধারণত রান্নায় সহজে গলে যায়।',
        'রান্নায় ঝাঁজ ও ঘ্রাণ বাড়াতে ব্যবহার করা হয়।',
        'সাধারণত বড় পরিমাণে ব্যবহার করা হয়।',
        'এটি রান্নার স্বাদকে আলাদা করে তোলে।',
    ];

    $comparisonRight = $campaign?->comparison_text['right'] ?? [
        'চুইঝাল গাছের গোড়া এবং গোড়া সংলগ্ন অংশকে এটা চুইঝাল বলা হয়।',
        'এটা চুইঝাল ফাইবার কম থাকায় রান্নায় ভালো ফ্লেভার দেয়।',
        'মাংস, ডাল ও তরকারিতে ব্যবহার করা যায়।',
        'মসলা হিসেবে স্বাদ ও ঘ্রাণ বাড়াতে ব্যবহার করা হয়।',
        'এটি সাধারণ খাবারকেও সুস্বাদু করে তোলে।',
    ];

    $comparisonMaxRows = max(count($comparisonLeft), count($comparisonRight));

    $serviceItems = collect($campaign?->service_items ?? [
        [
            'icon' => 'fas fa-award',
            'title' => 'অর্গানিক প্রোডাক্ট',
            'description' => 'আমাদের কাছে পাবেন সেরা মানের প্রিমিয়াম পণ্য।',
        ],
        [
            'icon' => 'fas fa-crown',
            'title' => 'প্রিমিয়াম কোয়ালিটি',
            'description' => 'সেরা কোয়ালিটির পণ্য সংগ্রহ করে সরবরাহ করা হয়।',
        ],
        [
            'icon' => 'fas fa-undo-alt',
            'title' => 'রিটার্ন পলিসি',
            'description' => 'সমস্যা হলে সহজ রিটার্ন ও রিপ্লেসমেন্ট সুবিধা।',
        ],
        [
            'icon' => 'fas fa-truck',
            'title' => 'ক্যাশ অন ডেলিভারি',
            'description' => 'পণ্য হাতে পেয়ে টাকা পরিশোধ করার সুবিধা।',
        ],
    ])->filter(fn ($item) => ! empty($item['title']))->values();

    $helpContent = $campaign?->help_content ?? [
        'title' => 'সাহায্য প্রয়োজন?',
        'description' => 'যেকোনো জিজ্ঞাসা ও অর্ডারজনিত সমস্যায় কল করুন আমাদের হেল্পলাইনে অথবা নক করুন আমাদের হোয়াটসঅ্যাপ বা ফেসবুক পেজে। আমরা আছি সকাল ১০ টা থেকে রাত ৮ টা পর্যন্ত আপনার সেবায়।',
        'button_text' => 'হেল্পলাইন',
    ];

    $reviewItems = $reviews->values();

    $phoneNumber = $campaign?->hero_phone
        ?: $valueOf($siteSetting, [
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

    $whatsappNumber = $campaign?->hero_whatsapp
        ?: $valueOf($siteSetting, [
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

    $phoneUrl = $makePhoneUrl($phoneNumber);
    $whatsappUrl = $makeWhatsappUrl($whatsappNumber);

    $serviceBannerImage = $campaign?->banner_image_url ?: null;

    $campaignGalleryImages = collect();

    try {
        if ($campaign && method_exists($campaign, 'getMedia')) {
            $campaignGalleryImages = $campaign->getMedia('campaign_product_gallery');
        }
    } catch (\Throwable $e) {
        $campaignGalleryImages = collect();
    }
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

.btn-success {
    background: var(--front-green);
    border-color: var(--front-green);
}

.btn-success:hover,
.btn-success:focus {
    background: var(--front-green-dark) !important;
    border-color: var(--front-green-dark) !important;
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
.help-icon-btn:hover {
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

.hero-video-box video,
.hero-video-box iframe {
    width: 100%;
    height: 500px;
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

.service-banner {
    position: relative;
    overflow: hidden;
    height: 265px;
    max-width: 1120px;
    margin: 70px auto 0;
    border-radius: 18px;
    background: var(--front-soft);
    box-shadow: var(--front-shadow);
}

.service-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gallery-section {
    background: #ffffff;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

.gallery-card {
    height: 220px;
    overflow: hidden;
    border-radius: 18px;
    background: #f8fafc;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.055);
    border: 1px solid #eef2f7;
}

.gallery-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: 0.25s ease;
}

.gallery-card:hover img {
    transform: scale(1.04);
}

.review-section {
    overflow: hidden;
    background: #ffffff;
}

.review-carousel-box {
    position: relative;
    max-width: 880px;
    margin: 0 auto;
    padding: 0 86px 62px;
}

.review-carousel-inner {
    max-width: 660px;
    margin: 0 auto;
    border: 1px solid #eef2f7;
    border-radius: 20px;
    background: linear-gradient(145deg, #ffffff, #f8fafc);
    box-shadow: var(--front-shadow);
    overflow: hidden;
}

.review-slide-card {
    min-height: 265px;
    padding: 44px 50px 40px;
}

.review-slide-card .stars {
    color: #f59e0b;
    margin-bottom: 22px;
    letter-spacing: 5px;
    font-size: 18px;
}

.review-slide-card p {
    color: #64748b;
    line-height: 1.9;
    font-size: 17px;
    margin-bottom: 34px;
}

.review-user {
    display: flex;
    align-items: center;
}

.review-user img {
    width: 62px;
    height: 62px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 16px;
    background: #e2e8f0;
    border: 4px solid #ffffff;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.10);
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

.review-control {
    position: absolute;
    top: 45%;
    transform: translateY(-50%);
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #e8eef5;
    color: #ffffff !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    opacity: 0.85;
    z-index: 4;
    text-decoration: none !important;
    transition: 0.2s ease;
}

.review-control:hover {
    background: var(--front-green);
    color: #ffffff !important;
    opacity: 1;
}

.review-control-prev {
    left: 70px;
}

.review-control-next {
    right: 70px;
}

.review-indicators {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    display: flex;
    justify-content: center;
    gap: 12px;
    padding-left: 0;
    margin: 0;
    list-style: none;
}

.review-indicators li {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #cbd5e1;
    cursor: pointer;
    transition: 0.2s ease;
}

.review-indicators li.active {
    width: 28px;
    background: var(--front-green);
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

.faq-body {
    color: var(--front-text);
    line-height: 1.8;
    padding: 0 26px 24px;
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
    min-height: 310px;
    padding: 66px 20px 60px;
    text-align: center;
    border-radius: 20px;
    border: 1px solid #eef2f7;
    box-shadow: var(--front-shadow);
    background: #f7fbf7;
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
}


.order-section .section-title {
    margin-bottom: 32px;
}

.order-panel {
    max-width: 1120px;
    margin: 0 auto;
}

.order-products-title,
.order-summary-title,
.delivery-form-title {
    color: var(--front-dark);
    font-size: 22px;
    font-weight: 900;
    margin-bottom: 18px;
}

.order-products-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 42px;
}

.order-product-card {
    min-height: 86px;
    display: flex;
    align-items: center;
    position: relative;
    cursor: pointer;
    padding: 10px;
    border: 1px solid #dce5ef;
    background: #f8fafc;
    border-radius: 12px;
    box-shadow: none;
    transition: 0.2s ease;
}

.order-product-card:hover {
    border-color: rgba(34, 197, 94, 0.45);
    background: #ffffff;
    transform: translateY(-1px);
}

.order-product-card.active {
    border-color: var(--front-green);
    box-shadow: 0 0 0 1px var(--front-green);
    background: #f0fdf4;
}

.order-product-card img {
    width: 70px;
    height: 62px;
    object-fit: cover;
    border-radius: 9px;
    margin-right: 10px;
    background: #e2e8f0;
    flex-shrink: 0;
}

.order-product-card h5 {
    color: var(--front-dark);
    font-size: 16px;
    line-height: 1.3;
    font-weight: 900;
    margin-bottom: 6px;
}

.order-product-card p {
    margin: 0;
    color: #94a3b8;
    font-size: 13px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.order-product-price {
    color: var(--front-dark);
    white-space: nowrap;
}

.order-product-weight {
    color: #94a3b8;
    white-space: nowrap;
}

.order-info-box {
    border-top: 2px dashed #dbe4ef;
    padding-top: 20px;
}

.delivery-area-select-wrap select {
    min-height: 48px;
    border-color: #cbd5e1;
    border-radius: 10px;
    color: #64748b;
    font-weight: 600;
}

.order-summary-card {
    position: sticky;
    top: 95px;
}

.summary-product {
    border-top: 2px dashed #dbe4ef;
    padding: 18px 0;
}

.summary-product-info img {
    width: 64px;
    height: 64px;
}

.summary-product-info h5 {
    font-size: 16px;
}

.summary-line {
    border-top: 2px dashed #dbe4ef;
    padding: 15px 0;
}

.summary-line strong {
    color: #16a34a;
    font-weight: 900;
}

.order-submit-btn {
    min-height: 52px;
    border-radius: 10px;
    font-size: 17px;
    font-weight: 900;
    background: var(--front-green);
    border-color: var(--front-green);
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
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .review-slide-card {
        width: 520px;
    }
}

@media (max-width: 767px) {
    .section-title {
        font-size: 28px;
    }

    .delivery-area-options {
        grid-template-columns: 1fr;
    }

    .help-title {
        font-size: 27px;
    }

    .gallery-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .gallery-card {
        height: 185px;
    }

    .review-carousel-box {
        padding: 0 54px 56px;
    }

    .review-slide-card {
        padding: 34px 28px 32px;
    }

    .review-control-prev {
        left: 20px;
    }

    .review-control-next {
        right: 20px;
    }

    .service-banner {
        height: 200px;
    }
}

@media (max-width: 575px) {
    .container {
        padding-left: 16px;
        padding-right: 16px;
    }

    .hero-video-box video,
    .hero-video-box iframe {
        height: 490px;
    }

    .summary-product-info {
        max-width: 68%;
    }

    .gallery-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .gallery-card {
        height: 155px;
    }

    .review-carousel-box {
        padding: 0 28px 52px;
    }

    .review-slide-card {
        min-height: 250px;
        padding: 28px 22px 30px;
    }

    .review-control {
        width: 34px;
        height: 34px;
        font-size: 13px;
    }

    .review-control-prev {
        left: 0;
    }

    .review-control-next {
        right: 0;
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
@if($campaign?->hero_section_status ?? true)
<section class="hero-section" id="video-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7 hero-content">
                <h1 class="hero-title">{{ $heroTitle }}</h1>

                <div class="hero-text">
                    {!! $heroSubtitle !!}
                </div>

                @if(($campaign?->benefits_section_status ?? true) && $benefits->isNotEmpty())
                <div class="hero-check-list">
                    @foreach($benefits as $benefit)
                    <div class="hero-check-item">
                        <i class="fas fa-check-circle"></i>
                        {{ $benefit }}
                    </div>
                    @endforeach
                </div>
                @endif

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
                        <i class="fas fa-shopping-cart mr-1"></i>
                        {{ $campaign?->button_text ?: 'অর্ডার করুন' }}
                    </a>

                    @if($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" target="_blank" class="side-action-btn" title="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    @endif

                    @if($phoneUrl)
                    <a href="{{ $phoneUrl }}" class="side-action-btn" title="Call">
                        <i class="fas fa-phone-alt"></i>
                    </a>
                    @endif
                </div>
            </div>

            @if($videoEmbedUrl || $videoFileUrl)
            <div class="col-lg-5">
                <div class="hero-video-box">
                    @if($videoEmbedUrl)
                    <iframe src="{{ $videoEmbedUrl }}" title="{{ $heroTitle }}" allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                    </iframe>
                    @elseif($videoFileUrl)
                    <video controls preload="metadata" poster="{{ $heroVideoPoster }}">
                        <source src="{{ $videoFileUrl }}" type="video/mp4">
                    </video>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- Category --}}
@if(($campaign?->category_section_status ?? true) && $categories->isNotEmpty())
<section class="section-space" id="category-section">
    <div class="container">
        <h2 class="section-title">{{ $categorySectionTitle }}</h2>

        <div class="row">
            @foreach($categories as $category)
            <div class="col-lg-3 col-md-4 col-6 mb-3">
                <div class="category-card">
                    <i class="fas fa-leaf"></i>
                    {{ $category->name }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Brand --}}
@if(($campaign?->category_section_status ?? true) && $brands->isNotEmpty())
<section class="pb-5" id="brand-section">
    <div class="container">
        <h2 class="section-title">{{ $brandSectionTitle }}</h2>

        <div class="row">
            @foreach($brands as $brand)
            <div class="col-lg-3 col-md-4 col-6 mb-3">
                <div class="brand-card">
                    <i class="fas fa-store"></i>
                    {{ $brand->name }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Products --}}
@if($campaign?->product_section_status ?? true)
<section class="section-space bg-white" id="products-section">
    <div class="container">
        <h2 class="section-title">{{ $productSectionTitle }}</h2>

        @if($products->isNotEmpty())
        <div class="filter-card mb-4">
            <div class="row">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="filter-title">{{ $categoryFilterTitle }}</div>

                    @foreach($categories as $category)
                    <button type="button"
                        class="filter-chip product-filter {{ (string) $defaultCategoryId === (string) $category->id ? 'active' : '' }}"
                        data-filter-type="category" data-filter-value="{{ $category->id }}">
                        {{ $category->name }}
                    </button>
                    @endforeach

                    <button type="button" class="filter-chip product-filter {{ $defaultCategoryId ? '' : 'active' }}"
                        data-filter-type="category" data-filter-value="all">
                        All Categories
                    </button>
                </div>

                <div class="col-lg-6">
                    <div class="filter-title">{{ $brandFilterTitle }}</div>

                    @foreach($brands as $brand)
                    <button type="button" class="filter-chip product-filter" data-filter-type="brand"
                        data-filter-value="{{ $brand->id }}">
                        {{ $brand->name }}
                    </button>
                    @endforeach

                    <button type="button" class="filter-chip product-filter active" data-filter-type="brand"
                        data-filter-value="all">
                        All Brands
                    </button>
                </div>
            </div>
        </div>
        @endif

        <div class="row" id="frontProductGrid">
            @forelse($products as $product)
            @php
            $productImage = $imageOf($product);
            $campaignPrice = (int) optional($product->pivot)->campaign_price;
            $productPrice = $campaignPrice > 0 ? $campaignPrice : (int) $product->new_price;
            $productOldPrice = $product->old_price ? (int) $product->old_price : null;
            $productWeight = $product->weight_size ?: ($product->weight ?? ($product->size ?? ''));
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

                            @if($productOldPrice && $productOldPrice > $productPrice)
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
                <div class="alert alert-warning text-center">
                    এই campaign-এ কোনো selected product পাওয়া যায়নি।
                </div>
            </div>
            @endforelse
        </div>
    </div>
</section>
@endif

{{-- Difference --}}
@if(($campaign?->comparison_section_status ?? true) && $comparisonMaxRows > 0)
<section class="section-space difference-section" id="difference-section">
    <div class="container">
        <h2 class="section-title">{{ $comparisonSectionTitle }}</h2>

        <div class="row align-items-center">
            <div class="col-lg-3 d-none d-lg-block">
                <img src="{{ $campaign?->image_one_url ?: $noImage }}" class="side-product-img"
                    alt="{{ $comparisonLeftTitle }}" onerror="this.onerror=null;this.src='{{ $noImage }}';">
            </div>

            <div class="col-lg-6">
                <h4 class="text-center font-weight-bold mb-4">
                    {{ $comparisonLeftTitle }} &nbsp;&nbsp; {{ $comparisonRightTitle }}
                </h4>

                <div class="table-responsive">
                    <table class="table difference-table mb-0">
                        <tbody>
                            @for($i = 0; $i < $comparisonMaxRows; $i++) <tr>
                                <td>{{ $comparisonLeft[$i] ?? '' }}</td>
                                <td>{{ $comparisonRight[$i] ?? '' }}</td>
                                </tr>
                                @endfor
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-3 d-none d-lg-block">
                <img src="{{ $campaign?->image_two_url ?: $noImage }}" class="side-product-img"
                    alt="{{ $comparisonRightTitle }}" onerror="this.onerror=null;this.src='{{ $noImage }}';">
            </div>
        </div>
    </div>
</section>
@endif

{{-- Services + Banner --}}
@if($campaign?->service_section_status ?? true)
<section class="section-space" id="services-section">
    <div class="container">
        <h2 class="section-title">{{ $serviceSectionTitle }}</h2>

        <div class="row">
            @foreach($serviceItems as $service)
            <div class="col-md-3 col-6 mb-3">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="{{ $service['icon'] ?? 'fas fa-check' }}"></i>
                    </div>
                    <h4>{{ $service['title'] ?? '' }}</h4>
                    <p>{{ $service['description'] ?? '' }}</p>
                </div>
            </div>
            @endforeach
        </div>

        @if($serviceBannerImage)
        <div class="service-banner">
            <img src="{{ $serviceBannerImage }}" alt="{{ $serviceSectionTitle }}"
                onerror="this.onerror=null;this.src='{{ $noImage }}';">
        </div>
        @endif
    </div>
</section>
@endif

{{-- Product Gallery --}}
@if(($campaign?->gallery_section_status ?? true) && $campaignGalleryImages->isNotEmpty())
    <section class="section-space gallery-section" id="gallery-section">
        <div class="container">
            <h2 class="section-title">{{ $gallerySectionTitle }}</h2>

            <div class="gallery-grid">
                @foreach($campaignGalleryImages as $media)
                    <div class="gallery-card">
                        <img src="{{ $media->getUrl() }}"
                             alt="{{ $gallerySectionTitle }} {{ $loop->iteration }}"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='{{ $noImage }}';">
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Reviews --}}
@if(($campaign?->review_section_status ?? true) && $reviewItems->isNotEmpty())
<section class="section-space review-section" id="reviews-section">
    <div class="container">
        <h2 class="section-title">{{ $reviewSectionTitle }}</h2>

        <div id="customerReviewCarousel" class="carousel slide review-carousel-box" data-ride="carousel"
            data-interval="3800" data-pause="hover">

            <div class="carousel-inner review-carousel-inner">
                @foreach($reviewItems as $review)
                @php
                $reviewImage = $imageOf($review);
                $reviewName = $review->customer_name ?? $review->name ?? 'Customer';
                $reviewLocation = $review->location ?? $review->designation ?? 'Happy Customer';
                $reviewText = $review->review_text ?? $review->comment ?? $review->message ?? '';
                $rating = (int) ($review->rating ?? 5);
                $rating = $rating > 0 ? min($rating, 5) : 5;
                @endphp

                <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                    <div class="review-slide-card">
                        <div class="stars">
                            @for($i = 1; $i <= 5; $i++) <i class="{{ $i <= $rating ? 'fas' : 'far' }} fa-star"></i>
                                @endfor
                        </div>

                        <p>{{ $reviewText }}</p>

                        <div class="review-user">
                            <img src="{{ $reviewImage }}" alt="{{ $reviewName }}"
                                onerror="this.onerror=null;this.src='{{ $noImage }}';">

                            <div>
                                <strong>{{ $reviewName }}</strong>
                                <span>{{ $reviewLocation }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            @if($reviewItems->count() > 1)
            <a class="review-control review-control-prev" href="#customerReviewCarousel" role="button" data-slide="prev"
                aria-label="Previous review">
                <i class="fas fa-chevron-left"></i>
            </a>

            <a class="review-control review-control-next" href="#customerReviewCarousel" role="button" data-slide="next"
                aria-label="Next review">
                <i class="fas fa-chevron-right"></i>
            </a>

            <ol class="review-indicators">
                @foreach($reviewItems as $review)
                <li data-target="#customerReviewCarousel" data-slide-to="{{ $loop->index }}"
                    class="{{ $loop->first ? 'active' : '' }}"></li>
                @endforeach
            </ol>
            @endif
        </div>
    </div>
</section>
@endif

{{-- FAQ --}}
@if(($campaign?->faq_section_status ?? true) && $faqs->isNotEmpty())
<section class="section-space bg-white" id="faq-section">
    <div class="container">
        <h2 class="section-title">{{ $faqSectionTitle }}</h2>

        <div class="faq-wrapper" id="faqAccordion">
            @foreach($faqs as $faq)
            @php
            $question = $faq->question ?? $faq->title ?? '';
            $answer = $faq->answer ?? $faq->description ?? '';
            @endphp

            <div class="faq-item">
                <button class="faq-button" type="button" data-toggle="collapse" data-target="#faq_{{ $faq->id }}"
                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                    <span>{{ $question }}</span>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div id="faq_{{ $faq->id }}" class="collapse {{ $loop->first ? 'show' : '' }}"
                    data-parent="#faqAccordion">
                    <div class="faq-body">
                        {!! $answer !!}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Order --}}
@if(($campaign?->order_section_status ?? true) && $campaign)
<section class="order-section" id="order-section">
    <div class="container">
        <h2 class="section-title">{{ $orderSectionTitle }}</h2>

        <form action="{{ route('campaign.order.store', $campaign->slug) }}" method="POST"
            class="checkout-form order-panel" id="campaignOrderForm">
            @csrf

            <div class="row">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h3 class="order-products-title">
                        প্রোডাক্ট নির্বাচন করুন
                    </h3>

                    <div class="order-products-grid">
                        @foreach($orderProducts as $product)
                        @php
                        $productImage = $imageOf($product);
                        $campaignPrice = (int) optional($product->pivot)->campaign_price;
                        $unitPrice = $campaignPrice > 0 ? $campaignPrice : (int) $product->new_price;
                        $weight = $product->weight_size ?: ($product->weight ?? ($product->size ?? ''));
                        @endphp

                        <div class="order-product-card" data-id="{{ $product->id }}" data-name="{{ $product->name }}"
                            data-price="{{ $unitPrice }}" data-weight="{{ $weight }}" data-image="{{ $productImage }}"
                            data-free-delivery="{{ $product->is_free_delivery ? 1 : 0 }}">
                            <img src="{{ $productImage }}" alt="{{ $product->name }}"
                                onerror="this.onerror=null;this.src='{{ $noImage }}';">

                            <div class="flex-grow-1">
                                <h5>{{ $product->name }}</h5>

                                <p>
                                    <span class="order-product-price">৳ {{ number_format($unitPrice) }}</span>

                                    @if($weight)
                                    <span class="order-product-weight">{{ $weight }}</span>
                                    @endif
                                </p>

                                @if($product->is_free_delivery)
                                <span class="badge badge-success mt-1">ফ্রি ডেলিভারি</span>
                                @endif
                            </div>

                            <span class="selected-check">
                                <i class="fas fa-check"></i>
                            </span>
                        </div>
                        @endforeach
                    </div>

                    <div class="order-info-box">
                        <h3 class="delivery-form-title">
                            {{ $campaign?->order_form_title ?: 'ডেলিভারি এড্রেস' }}
                        </h3>

                        @if($campaign?->order_form_subtitle)
                        <p class="text-muted mb-4">{{ $campaign->order_form_subtitle }}</p>
                        @endif

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
                            <textarea name="address" rows="2" class="form-control"
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
                    <div class="order-summary-card">
                        <h3 class="order-summary-title">অর্ডার সামারি</h3>

                        <div id="selectedProductsBox"></div>
                        <div id="hiddenProductInputs"></div>

                        <div class="summary-line">
                            <span>মোট</span>
                            <strong id="summarySubTotal">৳0</strong>
                        </div>

                        <div class="summary-line">
                            <span>ডেলিভারি চার্জ</span>
                            <strong id="summaryDeliveryCharge">৳100</strong>
                        </div>

                        <div class="summary-line">
                            <span>ক্যাশ অন ডেলিভারি চার্জ ১%</span>
                            <strong id="summaryCodCharge">৳0</strong>
                        </div>

                        <div class="summary-line grand-total">
                            <span>সর্বমোট</span>
                            <strong id="summaryGrandTotal">৳0</strong>
                        </div>

                        <div class="summary-line">
                            <span>ডেলিভারি মেথড</span>
                            <strong>ক্যাশ অন ডেলিভারি</strong>
                        </div>

                        <button type="submit" class="btn btn-success btn-block order-submit-btn mt-3">
                            <i class="fas fa-check-circle mr-2"></i>
                            অর্ডার প্রেস করুন
                        </button>

                        <p class="text-muted text-center mt-3">
                            বিশেষ দ্রষ্টব্য: ক্যাশ অন ডেলিভারি নেওয়ার সময় পণ্য মূল্য পরিশোধ করুন।
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endif

{{-- Help --}}
<section class="help-cta-section" id="contact-section">
    <div class="container">
        <div class="help-box">
            <h2 class="help-title">{{ $helpContent['title'] ?? 'সাহায্য প্রয়োজন?' }}</h2>

            <p class="help-text">
                {{ $helpContent['description'] ?? '' }}
            </p>

            <div class="help-actions">
                @if($phoneUrl)
                <a href="{{ $phoneUrl }}" class="help-main-btn">
                    <i class="fas fa-phone-alt mr-1"></i>
                    {{ $helpContent['button_text'] ?? 'হেল্পলাইন' }}
                </a>
                @endif

                @if($whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" class="help-icon-btn">
                    <i class="fab fa-whatsapp"></i>
                </a>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection

@push('js')
<script>
$(document).ready(function() {
    const noImage = @json($noImage);

    const deliveryCharges = {
        inside_dhaka: 100,
        outside_dhaka: 150
    };

    let selectedCategory = @json($defaultCategoryId ? (string) $defaultCategoryId : 'all');
    let selectedBrand = 'all';
    let selectedProducts = {};
    let suppressTracking = false;

    function money(amount) {
        return '৳' + Number(amount || 0).toLocaleString('en-US');
    }

    function number(value) {
        value = Number(value || 0);
        return isNaN(value) ? 0 : value;
    }

    function trackingReady() {
        return typeof window.SFATracking !== 'undefined';
    }

    function filterProducts() {
        let visibleCount = 0;

        $('.product-grid-item').each(function() {
            const item = $(this);
            const categoryId = String(item.data('category'));
            const brandId = String(item.data('brand'));

            const categoryMatch = selectedCategory === 'all' || selectedCategory === categoryId;
            const brandMatch = selectedBrand === 'all' || selectedBrand === brandId;

            if (categoryMatch && brandMatch) {
                item.show();
                visibleCount++;
            } else {
                item.hide();
            }
        });

        $('#noProductFoundBox').remove();

        if (visibleCount === 0) {
            $('#frontProductGrid').append(`
                <div class="col-12" id="noProductFoundBox">
                    <div class="alert alert-warning text-center">
                        এই category এবং brand অনুযায়ী কোনো product পাওয়া যায়নি।
                    </div>
                </div>
            `);
        }
    }

    $(document).on('click', '.product-filter', function() {
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
        filterProducts();
    });

    function hasFreeDeliveryProduct() {
        return Object.values(selectedProducts).some(function(item) {
            return item.isFreeDelivery === true;
        });
    }

    function updateDeliveryAreaView() {
        if (hasFreeDeliveryProduct()) {
            $('#deliveryAreaOptions, #deliveryAreaSelectWrap').addClass('d-none');
            $('#freeDeliveryAreaBox').removeClass('d-none');
        } else {
            $('#deliveryAreaOptions, #deliveryAreaSelectWrap').removeClass('d-none');
            $('#freeDeliveryAreaBox').addClass('d-none');
        }
    }

    function addProductFromCard(card) {
        const id = String(card.data('id'));

        selectedProducts[id] = {
            id: id,
            name: String(card.data('name') || ''),
            price: number(card.data('price') || 0),
            weight: String(card.data('weight') || ''),
            image: card.data('image') || noImage,
            isFreeDelivery: Number(card.data('free-delivery') || 0) === 1,
            quantity: selectedProducts[id] ? Number(selectedProducts[id].quantity || 1) : 1
        };

        return selectedProducts[id];
    }

    function itemPayload(item) {
        return {
            item_id: String(item.id || ''),
            item_name: String(item.name || ''),
            price: number(item.price || 0),
            quantity: Number(item.quantity || 1)
        };
    }

    function getCheckoutItems() {
        return Object.values(selectedProducts).map(function(item) {
            return itemPayload(item);
        }).filter(function(item) {
            return item.item_id || item.item_name;
        });
    }

    function getAllOrderItems() {
        return $('.order-product-card').map(function() {
            const card = $(this);

            return {
                item_id: String(card.data('id') || ''),
                item_name: String(card.data('name') || ''),
                price: number(card.data('price') || 0),
                quantity: 1
            };
        }).get().filter(function(item) {
            return item.item_id || item.item_name;
        });
    }

    function getOrderTotals() {
        let subTotal = 0;

        Object.values(selectedProducts).forEach(function(item) {
            subTotal += number(item.price || 0) * Number(item.quantity || 1);
        });

        const hasAnyFreeDeliveryProduct = hasFreeDeliveryProduct();

        const deliveryArea = $('#deliveryAreaSelect').val()
            || $('input[name="delivery_area"]:checked').val()
            || 'inside_dhaka';

        const deliveryCharge = hasAnyFreeDeliveryProduct ? 0 : number(deliveryCharges[deliveryArea] || 0);
        const codCharge = 0;
        const grandTotal = subTotal + deliveryCharge + codCharge;

        return {
            subTotal: subTotal,
            deliveryCharge: deliveryCharge,
            codCharge: codCharge,
            grandTotal: grandTotal,
            hasAnyFreeDeliveryProduct: hasAnyFreeDeliveryProduct
        };
    }

    function trackViewContentOnce() {
        if (!trackingReady()) {
            return;
        }

        const items = getAllOrderItems();

        if (!items.length) {
            return;
        }

        const value = items.reduce(function(total, item) {
            return total + (number(item.price) * Number(item.quantity || 1));
        }, 0);

        window.SFATracking.viewContent({
            currency: 'BDT',
            value: value,
            content_type: 'product',
            content_id: items[0].item_id,
            content_name: items[0].item_name,
            items: items
        });
    }

    function trackAddToCart(item) {
        if (!trackingReady() || !item || suppressTracking) {
            return;
        }

        window.SFATracking.addToCart({
            item_id: item.id,
            item_name: item.name,
            price: item.price,
            quantity: item.quantity || 1,
            currency: 'BDT'
        });
    }

    function trackBeginCheckout() {
        if (!trackingReady()) {
            return;
        }

        const items = getCheckoutItems();

        if (!items.length) {
            return;
        }

        const totals = getOrderTotals();

        window.SFATracking.beginCheckout({
            currency: 'BDT',
            value: totals.grandTotal,
            shipping: totals.deliveryCharge,
            tax: totals.codCharge,
            items: items
        });
    }

    function renderSummary() {
        let itemsHtml = '';
        let inputsHtml = '';
        let index = 0;

        Object.values(selectedProducts).forEach(function(item) {
            itemsHtml += `
                <div class="summary-product" data-id="${item.id}">
                    <div class="summary-product-info">
                        <img src="${item.image}" alt="${item.name}" onerror="this.onerror=null;this.src='${noImage}';">
                        <div>
                            <h5>${item.name}</h5>
                            <span>${item.weight}</span><br>
                            <strong>${money(item.price)}</strong>
                            ${item.isFreeDelivery ? '<br><span class="badge badge-success mt-1">ফ্রি ডেলিভারি</span>' : ''}
                        </div>
                    </div>

                    <div class="d-flex align-items-center">
                        <span class="remove-summary-item" data-id="${item.id}">
                            <i class="fas fa-trash-alt"></i>
                        </span>

                        <select class="summary-qty" data-id="${item.id}">
                            ${[1,2,3,4,5,6,7,8,9,10].map(function(qty) {
                                return `<option value="${qty}" ${qty === Number(item.quantity) ? 'selected' : ''}>${qty}</option>`;
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

        if (!Object.keys(selectedProducts).length) {
            itemsHtml = `
                <div class="alert alert-warning">
                    অর্ডার করার জন্য কমপক্ষে একটি product select করুন।
                </div>
            `;
        }

        $('#selectedProductsBox').html(itemsHtml);
        $('#hiddenProductInputs').html(inputsHtml);

        const totals = getOrderTotals();

        $('#summarySubTotal').text(money(totals.subTotal));
        $('#summaryDeliveryCharge').text(totals.hasAnyFreeDeliveryProduct ? 'Free Delivery' : money(totals.deliveryCharge));
        $('#summaryCodCharge').text(money(totals.codCharge));
        $('#summaryGrandTotal').text(money(totals.grandTotal));

        updateDeliveryAreaView();
    }

    $(document).on('click', '.order-product-card', function() {
        const card = $(this);
        const id = String(card.data('id'));

        if (selectedProducts[id]) {
            delete selectedProducts[id];
            card.removeClass('active');
        } else {
            const item = addProductFromCard(card);
            card.addClass('active');
            trackAddToCart(item);
        }

        renderSummary();
    });

    $(document).on('click', '.add-product-to-order', function() {
        const productId = String($(this).data('product-id'));
        const orderCard = $('.order-product-card[data-id="' + productId + '"]');

        if (orderCard.length) {
            const item = addProductFromCard(orderCard);

            orderCard.addClass('active');
            renderSummary();
            trackAddToCart(item);

            $('html, body').animate({
                scrollTop: $('#order-section').offset().top - 80
            }, 500);
        }
    });

    $(document).on('click', 'a[href="#order-section"]', function() {
        trackBeginCheckout();
    });

    $(document).on('click', '.remove-summary-item', function() {
        const id = String($(this).data('id'));

        delete selectedProducts[id];

        $('.order-product-card[data-id="' + id + '"]').removeClass('active');

        renderSummary();
    });

    $(document).on('change', '.summary-qty', function() {
        const id = String($(this).data('id'));
        const quantity = Number($(this).val() || 1);

        if (selectedProducts[id]) {
            selectedProducts[id].quantity = quantity;
        }

        renderSummary();
    });

    $(document).on('change', '#deliveryAreaSelect, input[name="delivery_area"]', function() {
        if ($(this).is('input[name="delivery_area"]')) {
            $('.delivery-area-card').removeClass('active');
            $(this).closest('.delivery-area-card').addClass('active');
        }

        renderSummary();
    });

    $('#campaignOrderForm').on('submit', function(e) {
        if (!Object.keys(selectedProducts).length) {
            e.preventDefault();
            alert('দয়া করে কমপক্ষে একটি product select করুন।');
            return false;
        }

        trackBeginCheckout();

        return true;
    });

    suppressTracking = true;
    $('.order-product-card').first().trigger('click');
    suppressTracking = false;

    filterProducts();
    trackViewContentOnce();
});
</script>
@endpush