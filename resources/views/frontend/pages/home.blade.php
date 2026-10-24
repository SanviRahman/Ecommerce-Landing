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

    $siteLogoUrl = null;
    $siteFaviconUrl = null;

    try {
        if ($siteSetting && method_exists($siteSetting, 'getFirstMediaUrl')) {
            $siteLogoUrl = $siteSetting->getFirstMediaUrl('site_logo') ?: null;
            $siteFaviconUrl = $siteSetting->getFirstMediaUrl('site_favicon') ?: null;
        }
    } catch (\Throwable $e) {
        $siteLogoUrl = null;
        $siteFaviconUrl = null;
    }

    $siteLogoUrl = $siteLogoUrl ?: ($siteSetting->logo ?? null);
    $siteFaviconUrl = $siteFaviconUrl ?: ($siteSetting->favicon ?? null);

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

    $categories = collect($categories ?? []);
    $brands = collect($brands ?? []);
    $products = collect($products ?? []);
    $orderProducts = collect($orderProducts ?? []);
    $reviews = collect($reviews ?? []);
    $faqs = collect($faqs ?? []);

    $shippingCharges = collect($shippingCharges ?? []);

    /*
     |--------------------------------------------------------------------------
     | Safe Order Form Token Fallback
     |--------------------------------------------------------------------------
     | /campaign/{slug} page CampaignPageController দিয়ে load হয়। কোনো old cache
     | বা missing controller variable থাকলেও token blank থাকবে না।
     */
    if ($campaign && empty($orderFormToken)) {
        $tokens = session()->get('campaign_order_form_tokens', []);

        if (! is_array($tokens)) {
            $tokens = [];
        }

        $orderFormToken = \Illuminate\Support\Str::random(40);
        $tokens[] = $orderFormToken;

        session()->put('campaign_order_form_tokens', array_slice(array_values(array_unique(array_filter($tokens))), -10));
    }

    if ($shippingCharges->isEmpty() && class_exists(\App\Models\ShippingCharge::class)) {
        try {
            $shippingCharges = \App\Models\ShippingCharge::resolvedForCampaign(
                $campaign?->id ? (int) $campaign->id : null
            );
        } catch (\Throwable $e) {
            $shippingCharges = collect();
        }
    }

    $defaultShippingCharge = $shippingCharges->isNotEmpty()
        ? (int) $shippingCharges->first()->delivery_charge
        : 0;

    $defaultCategoryId = 'all';
    $defaultBrandId = 'all';

    $heroImage = $campaign
        ? ($campaign->image_three_url ?: ($campaign->banner_image_url ?: $noImage))
        : $noImage;

    $heroVideoAutoplay = (bool) ($campaign?->hero_video_autoplay ?? false);
    $heroVideoMuted = (bool) ($campaign?->hero_video_muted ?? false);

    /*
     |--------------------------------------------------------------------------
     | Hero Media Priority
     |--------------------------------------------------------------------------
     | 1. Embed Video URL
     | 2. Multiple Hero Slider Images
     | 3. Uploaded Campaign Video
     | 4. Fallback Image
     */
    $heroEmbedUrl = trim((string) ($campaign?->embed_video_url ?: ''));
    $heroSliderImages = collect();

    try {
        if ($campaign && method_exists($campaign, 'getMedia')) {
            $heroSliderImages = $campaign->getMedia('hero_slider_images');
        }
    } catch (\Throwable $e) {
        $heroSliderImages = collect();
    }

    $uploadedCampaignVideoUrl = null;

    try {
        if ($campaign && method_exists($campaign, 'getFirstMediaUrl')) {
            $uploadedCampaignVideoUrl = $campaign->getFirstMediaUrl('campaign_video') ?: null;
        }
    } catch (\Throwable $e) {
        $uploadedCampaignVideoUrl = null;
    }

    $uploadedCampaignVideoUrl = $uploadedCampaignVideoUrl ?: $valueOf($campaign, [
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

    $youtubeEmbedFromUrl = function (?string $url) use ($heroVideoAutoplay, $heroVideoMuted): ?string {
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
            'autoplay' => $heroVideoAutoplay ? 1 : 0,
            // Browser-safe autoplay starts muted; JS restores sound after interaction when Muted is OFF.
            'mute' => ($heroVideoAutoplay || $heroVideoMuted) ? 1 : 0,
            'modestbranding' => 1,
            'playsinline' => 1,
            'enablejsapi' => 1,
        ];

        if ($start > 0) {
            $params['start'] = $start;
        }

        return 'https://www.youtube-nocookie.com/embed/' . $videoId . '?' . http_build_query($params);
    };

    $facebookEmbedFromUrl = function (?string $url) use ($heroVideoAutoplay): ?string {
        if (! $url) {
            return null;
        }

        $url = trim($url);

        if (\Illuminate\Support\Str::contains($url, ['facebook.com/plugins/video'])) {
            $parts = parse_url($url);
            $query = [];

            if (! empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }

            $query['autoplay'] = $heroVideoAutoplay ? 'true' : 'false';

            return 'https://www.facebook.com/plugins/video.php?' . http_build_query($query);
        }

        return 'https://www.facebook.com/plugins/video.php?' . http_build_query([
            'href' => $url,
            'show_text' => 'false',
            'width' => 560,
            'autoplay' => $heroVideoAutoplay ? 'true' : 'false',
        ]);
    };

    if ($heroEmbedUrl) {
        if (\Illuminate\Support\Str::contains($heroEmbedUrl, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'])) {
            $videoEmbedUrl = $youtubeEmbedFromUrl($heroEmbedUrl);
        } elseif (\Illuminate\Support\Str::contains($heroEmbedUrl, ['facebook.com/plugins/video'])) {
            $videoEmbedUrl = $facebookEmbedFromUrl($heroEmbedUrl);
        } elseif (\Illuminate\Support\Str::contains($heroEmbedUrl, ['facebook.com', 'fb.watch'])) {
            $videoEmbedUrl = $facebookEmbedFromUrl($heroEmbedUrl);
        } else {
            $videoFileUrl = \Illuminate\Support\Str::startsWith($heroEmbedUrl, ['http://', 'https://', '/'])
                ? $heroEmbedUrl
                : \Illuminate\Support\Facades\Storage::url($heroEmbedUrl);
        }
    }

    $fallbackVideoFileUrl = null;

    if (! $videoEmbedUrl && ! $videoFileUrl && $heroSliderImages->isEmpty() && $uploadedCampaignVideoUrl) {
        if (\Illuminate\Support\Str::contains($uploadedCampaignVideoUrl, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'])) {
            $videoEmbedUrl = $youtubeEmbedFromUrl($uploadedCampaignVideoUrl);
        } elseif (\Illuminate\Support\Str::contains($uploadedCampaignVideoUrl, ['facebook.com/plugins/video'])) {
            $videoEmbedUrl = $facebookEmbedFromUrl($uploadedCampaignVideoUrl);
        } elseif (\Illuminate\Support\Str::contains($uploadedCampaignVideoUrl, ['facebook.com', 'fb.watch'])) {
            $videoEmbedUrl = $facebookEmbedFromUrl($uploadedCampaignVideoUrl);
        } else {
            $fallbackVideoFileUrl = \Illuminate\Support\Str::startsWith($uploadedCampaignVideoUrl, ['http://', 'https://', '/'])
                ? $uploadedCampaignVideoUrl
                : \Illuminate\Support\Facades\Storage::url($uploadedCampaignVideoUrl);

            $videoFileUrl = $fallbackVideoFileUrl;
        }
    }

    $videoFileMimeType = 'video/mp4';

    if ($videoFileUrl) {
        $videoPath = (string) (parse_url($videoFileUrl, PHP_URL_PATH) ?: $videoFileUrl);
        $videoExtension = strtolower((string) pathinfo($videoPath, PATHINFO_EXTENSION));

        $videoFileMimeType = match ($videoExtension) {
            'webm' => 'video/webm',
            'ogg', 'ogv' => 'video/ogg',
            'm4v' => 'video/x-m4v',
            default => 'video/mp4',
        };
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

    $heroStarCount = (int) ($sectionTitles['hero_star_count'] ?? 5);
    $heroStarCount = max(1, min(5, $heroStarCount));

    $heroRatingText = $sectionTitles['hero_rating_text'] ?? '৩০,০০০ হাজারও অধিক গ্রাহকের কাছে<br>আমরা হয়েছি জনপ্রিয়';

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

    /*
     |--------------------------------------------------------------------------
     | Campaign FAQ & Review Data
     |--------------------------------------------------------------------------
     | Campaign form থেকে add/update করা FAQ ও Review home blade-এ আগে show হবে।
     | Controller relation eager load না করলেও blade safe fallback হিসেবে relation/query
     | থেকে campaign-specific active data collect করবে। Campaign-specific data না থাকলে
     | পুরোনো/global $reviews এবং $faqs data fallback হিসেবে show হবে।
     */
    $isVisibleItem = function ($item): bool {
        if (is_array($item)) {
            return array_key_exists('status', $item) ? (bool) $item['status'] : true;
        }

        return isset($item->status) ? (bool) $item->status : true;
    };

    $campaignReviewItems = collect();

    try {
        if ($campaign && method_exists($campaign, 'relationLoaded') && $campaign->relationLoaded('reviews')) {
            $campaignReviewItems = collect($campaign->getRelation('reviews'));
        } elseif ($campaign && method_exists($campaign, 'reviews')) {
            $campaignReviewItems = $campaign->reviews()
                ->where('status', true)
                ->latest()
                ->get();
        }
    } catch (\Throwable $e) {
        $campaignReviewItems = collect();
    }

    $reviewItems = ($campaignReviewItems->isNotEmpty() ? $campaignReviewItems : $reviews)
        ->filter(function ($review) use ($isVisibleItem) {
            if (! $isVisibleItem($review)) {
                return false;
            }

            $name = is_array($review)
                ? ($review['customer_name'] ?? $review['name'] ?? '')
                : ($review->customer_name ?? $review->name ?? '');

            $text = is_array($review)
                ? ($review['review_text'] ?? $review['comment'] ?? $review['message'] ?? '')
                : ($review->review_text ?? $review->comment ?? $review->message ?? '');

            return trim((string) $name) !== '' || trim((string) $text) !== '';
        })
        ->values();

    $campaignFaqItems = collect();

    try {
        if ($campaign && method_exists($campaign, 'relationLoaded') && $campaign->relationLoaded('faqs')) {
            $campaignFaqItems = collect($campaign->getRelation('faqs'));
        } elseif ($campaign && method_exists($campaign, 'faqs')) {
            $campaignFaqItems = $campaign->faqs()
                ->where('status', true)
                ->orderBy('sort_order')
                ->latest()
                ->get();
        }
    } catch (\Throwable $e) {
        $campaignFaqItems = collect();
    }

    $faqItems = ($campaignFaqItems->isNotEmpty() ? $campaignFaqItems : $faqs)
        ->filter(function ($faq) use ($isVisibleItem) {
            if (! $isVisibleItem($faq)) {
                return false;
            }

            $question = is_array($faq)
                ? ($faq['question'] ?? $faq['title'] ?? '')
                : ($faq->question ?? $faq->title ?? '');

            $answer = is_array($faq)
                ? ($faq['answer'] ?? $faq['description'] ?? '')
                : ($faq->answer ?? $faq->description ?? '');

            return trim((string) $question) !== '' || trim((string) $answer) !== '';
        })
        ->sortBy(function ($faq, $index) {
            if (is_array($faq)) {
                return $faq['sort_order'] ?? $index;
            }

            return $faq->sort_order ?? $index;
        })
        ->values();

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

    $serviceBannerImage = $campaign?->image_three_url ?: ($campaign?->banner_image_url ?: null);

    $campaignGalleryImages = collect();

    try {
        if ($campaign && method_exists($campaign, 'getMedia')) {
            $campaignGalleryImages = $campaign->getMedia('campaign_product_gallery');
        }
    } catch (\Throwable $e) {
        $campaignGalleryImages = collect();
    }

    /*
     |--------------------------------------------------------------------------
     | Frontend Section Active Flags
     |--------------------------------------------------------------------------
     | Campaign form-er section active/inactive switch onujayi frontend section,
     | section title/header and related action link show/hide hobe.
     */
    $isHeroSectionActive = (bool) ($campaign?->hero_section_status ?? true);
    $isBenefitsSectionActive = (bool) ($campaign?->benefits_section_status ?? true);
    $isCategorySectionActive = (bool) ($campaign?->category_section_status ?? true) && $categories->isNotEmpty();
    $isBrandSectionActive = (bool) ($campaign?->category_section_status ?? true) && $brands->isNotEmpty();
    $isProductFilterActive = (bool) ($campaign?->product_section_status ?? true);
    $isProductSectionActive = $products->isNotEmpty();
    $isComparisonSectionActive = (bool) ($campaign?->comparison_section_status ?? true) && $comparisonMaxRows > 0;
    $isServiceSectionActive = (bool) ($campaign?->service_section_status ?? true);
    $isGallerySectionActive = (bool) ($campaign?->gallery_section_status ?? true) && $campaignGalleryImages->isNotEmpty();
    $isReviewSectionActive = (bool) ($campaign?->review_section_status ?? true) && $reviewItems->isNotEmpty();
    $isFaqSectionActive = (bool) ($campaign?->faq_section_status ?? true) && $faqItems->isNotEmpty();
    $isHelpSectionActive = (bool) ($campaign?->help_section_status ?? true);
    $isOrderSectionActive = (bool) ($campaign?->order_section_status ?? true) && (bool) $campaign;
    $isOrderTrackingSectionActive = (bool) ($campaign?->order_tracking_section_status ?? true) && (bool) $campaign;

    $trackingOrders = $trackingOrders ?? null;
    $trackingSearchedPhone = $trackingSearchedPhone ?? null;

    $orderTrackingStatusLabels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'complete_invoice' => 'Invoice Complete',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled',
        'fake' => 'Fake',
        'stock_out' => 'Stock Out',
    ];

@endphp

@if(! $campaign)
    @section('title', $pageTitle)
    @section('meta_description', $pageDescription)

    @section('content')
        {{-- No active campaign available. Client requirement অনুযায়ী home content blank রাখা হয়েছে। --}}
    @endsection
@else

@section('title', $pageTitle)
@section('meta_description', $pageDescription)

@push('head')
    @if($siteFaviconUrl)
        <link rel="icon" href="{{ $siteFaviconUrl }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $siteFaviconUrl }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $siteFaviconUrl }}">
    @endif
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset('frontend/css/home.css') }}?v=20261024-frontend2">
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

{{-- Order Warning Popup --}}
<div class="order-popup-overlay" id="orderWarningPopup" aria-hidden="true">
    <div class="order-popup-box" role="dialog" aria-modal="true" aria-labelledby="orderWarningPopupTitle">
        <div class="order-popup-top">
            <div class="order-popup-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="order-popup-title" id="orderWarningPopupTitle">প্রোডাক্ট সিলেক্ট করুন</h3>
            <p class="order-popup-message" id="orderWarningPopupMessage">
                দয়া করে কমপক্ষে একটি product select করুন।
            </p>
        </div>
        <div class="order-popup-actions">
            <button type="button" class="order-popup-btn" id="orderWarningPopupClose">ঠিক আছে</button>
        </div>
    </div>
</div>

{{-- Hero --}}
@if($isHeroSectionActive)
<section class="hero-section" id="video-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7 hero-content">
                <h1 class="hero-title">{{ $heroTitle }}</h1>

                <div class="hero-text">
                    {!! $heroSubtitle !!}
                </div>

                @if($isBenefitsSectionActive && $benefits->isNotEmpty())
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
                    @for($star = 1; $star <= $heroStarCount; $star++)
                        <i class="fas fa-star"></i>
                    @endfor
                </div>

                <div class="hero-rating-text mb-4">
                    {!! $heroRatingText !!}
                </div>

                <div class="hero-actions">
                    @if($isOrderSectionActive)
                        <a href="#order-section" class="btn btn-success">
                            <i class="fas fa-shopping-cart mr-1"></i>
                            {{ $campaign?->button_text ?: 'অর্ডার করুন' }}
                        </a>
                    @endif

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

            <div class="col-lg-5 hero-video-col">
                @if($videoEmbedUrl || $videoFileUrl)
                    <div class="hero-video-box">
                        @if($videoEmbedUrl)
                            <iframe id="campaignHeroEmbedVideo"
                                    src="{{ $videoEmbedUrl }}"
                                    title="{{ $heroTitle }}"
                                    data-autoplay="{{ $heroVideoAutoplay ? '1' : '0' }}"
                                    data-admin-muted="{{ $heroVideoMuted ? '1' : '0' }}"
                                    allowfullscreen
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                            </iframe>
                        @elseif($videoFileUrl)
                            <video id="campaignHeroVideo"
                                   controls
                                   playsinline
                                   preload="{{ $heroVideoAutoplay ? 'auto' : 'metadata' }}"
                                   poster="{{ $heroVideoPoster }}"
                                   data-autoplay="{{ $heroVideoAutoplay ? '1' : '0' }}"
                                   data-admin-muted="{{ $heroVideoMuted ? '1' : '0' }}"
                                   @if($heroVideoAutoplay) autoplay @endif
                                   @if($heroVideoMuted) muted @endif>
                                <source src="{{ $videoFileUrl }}" type="{{ $videoFileMimeType }}">
                                Your browser does not support HTML5 video playback.
                            </video>
                        @endif
                    </div>
                @elseif($heroSliderImages->isNotEmpty())
                    <div class="hero-slider-box">
                        <div id="heroMediaSlider" class="carousel slide" data-ride="carousel" data-interval="3500">
                            @if($heroSliderImages->count() > 1)
                                <ol class="carousel-indicators">
                                    @foreach($heroSliderImages as $media)
                                        <li data-target="#heroMediaSlider"
                                            data-slide-to="{{ $loop->index }}"
                                            class="{{ $loop->first ? 'active' : '' }}">
                                        </li>
                                    @endforeach
                                </ol>
                            @endif

                            <div class="carousel-inner">
                                @foreach($heroSliderImages as $media)
                                    <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                                        <img src="{{ $media->getUrl() }}"
                                             alt="{{ $heroTitle }}"
                                             loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                                    </div>
                                @endforeach
                            </div>

                            @if($heroSliderImages->count() > 1)
                                <a class="carousel-control-prev" href="#heroMediaSlider" role="button" data-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="sr-only">Previous</span>
                                </a>

                                <a class="carousel-control-next" href="#heroMediaSlider" role="button" data-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="sr-only">Next</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="hero-media-fallback-box">
                        <img src="{{ $heroImage }}" alt="{{ $heroTitle }}">
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endif

{{-- Category --}}
@if($isCategorySectionActive)
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
@if($isBrandSectionActive)
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
@if($isProductSectionActive)
<section class="section-space bg-white" id="products-section">
    <div class="container">
        <h2 class="section-title">{{ $productSectionTitle }}</h2>

        @if($isProductFilterActive && $products->isNotEmpty())
        <div class="filter-card mb-4">
            <div class="row">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="filter-title">{{ $categoryFilterTitle }}</div>

                    <button type="button"
                        class="filter-chip product-filter active"
                        data-filter-type="category"
                        data-filter-value="all">
                        All Categories
                    </button>

                    @foreach($categories as $category)
                    <button type="button"
                        class="filter-chip product-filter"
                        data-filter-type="category"
                        data-filter-value="{{ $category->id }}">
                        {{ $category->name }}
                    </button>
                    @endforeach
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

            <div class="col-6 col-lg-3 col-md-4 col-sm-6 mb-4 product-grid-item" data-category="{{ $product->category_id }}"
                data-brand="{{ $product->brand_id ?: 'none' }}">
                <div class="product-card">
                    <img src="{{ $productImage }}" class="product-image" alt="{{ $product->name }}"
                        data-image-fallback="{{ $noImage }}">

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

                        @if($isOrderSectionActive)
                            <button type="button"
                                class="btn btn-success btn-block add-order-btn add-product-to-order {{ $product->is_free_delivery ? 'free-delivery-btn' : '' }}"
                                data-product-id="{{ $product->id }}">
                                <i class="fas fa-cart-plus mr-1"></i>
                                {{ $product->is_free_delivery ? 'ফ্রি ডেলিভারিতে অর্ডার করুন' : 'অর্ডারে যোগ করুন' }}
                            </button>
                        @endif
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
@if($isComparisonSectionActive)
<section class="section-space difference-section" id="difference-section">
    <div class="container">
        <h2 class="section-title">{{ $comparisonSectionTitle }}</h2>

        <div class="row align-items-center">
            <div class="col-lg-3 difference-desktop-image">
                <img src="{{ $campaign?->image_one_url ?: $noImage }}"
                     class="side-product-img"
                     alt="{{ $comparisonLeftTitle }}"
                     data-image-fallback="{{ $noImage }}">
            </div>

            <div class="col-lg-6">
                <div class="difference-mobile-images d-none">
                    <img src="{{ $campaign?->image_one_url ?: $noImage }}"
                         alt="{{ $comparisonLeftTitle }}"
                         data-image-fallback="{{ $noImage }}">

                    <img src="{{ $campaign?->image_two_url ?: $noImage }}"
                         alt="{{ $comparisonRightTitle }}"
                         data-image-fallback="{{ $noImage }}">
                </div>

                <h4 class="text-center font-weight-bold mb-4">
                    {{ $comparisonLeftTitle }} &nbsp;&nbsp; {{ $comparisonRightTitle }}
                </h4>

                <div class="table-responsive">
                    <table class="table difference-table mb-0">
                        <tbody>
                            @for($i = 0; $i < $comparisonMaxRows; $i++)
                                <tr>
                                    <td>{{ $comparisonLeft[$i] ?? '' }}</td>
                                    <td>{{ $comparisonRight[$i] ?? '' }}</td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-3 difference-desktop-image">
                <img src="{{ $campaign?->image_two_url ?: $noImage }}"
                     class="side-product-img"
                     alt="{{ $comparisonRightTitle }}"
                     data-image-fallback="{{ $noImage }}">
            </div>
        </div>
    </div>
</section>
@endif

{{-- Services + Banner --}}
@if($isServiceSectionActive)
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
                data-image-fallback="{{ $noImage }}">
        </div>
        @endif
    </div>
</section>
@endif

{{-- Product Gallery --}}
@if($isGallerySectionActive)
    <section class="section-space gallery-section" id="gallery-section">
        <div class="container">
            <h2 class="section-title">{{ $gallerySectionTitle }}</h2>

            <div class="gallery-grid">
                @foreach($campaignGalleryImages as $media)
                    <div class="gallery-card">
                        <img src="{{ $media->getUrl() }}"
                             alt="{{ $gallerySectionTitle }} {{ $loop->iteration }}"
                             loading="lazy"
                             data-image-fallback="{{ $noImage }}">
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Reviews --}}
@if($isReviewSectionActive)
<section class="section-space review-section" id="reviews-section">
    <div class="container">
        <h2 class="section-title">{{ $reviewSectionTitle }}</h2>

        <div id="customerReviewCarousel" class="carousel slide review-carousel-box" data-ride="carousel"
            data-interval="3800" data-pause="hover">

            <div class="carousel-inner review-carousel-inner">
                @foreach($reviewItems as $review)
                @php
                $reviewImage = is_array($review)
                    ? ($review['image_url'] ?? $review['customer_image'] ?? $noImage)
                    : $imageOf($review);

                $reviewName = is_array($review)
                    ? ($review['customer_name'] ?? $review['name'] ?? 'Customer')
                    : ($review->customer_name ?? $review->name ?? 'Customer');

                $reviewLocation = is_array($review)
                    ? ($review['location'] ?? $review['designation'] ?? 'Happy Customer')
                    : ($review->location ?? $review->designation ?? 'Happy Customer');

                $reviewText = is_array($review)
                    ? ($review['review_text'] ?? $review['comment'] ?? $review['message'] ?? '')
                    : ($review->review_text ?? $review->comment ?? $review->message ?? '');

                $reviewSocialLink = is_array($review)
                    ? ($review['social_link'] ?? null)
                    : ($review->social_link ?? null);

                $rating = (int) (is_array($review) ? ($review['rating'] ?? 5) : ($review->rating ?? 5));
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
                                data-image-fallback="{{ $noImage }}">

                            <div>
                                <strong>{{ $reviewName }}</strong>
                                <span>{{ $reviewLocation }}</span>

                                @if(! empty($reviewSocialLink))
                                    <a href="{{ $reviewSocialLink }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="review-social-link">
                                        <i class="fas fa-external-link-alt"></i>
                                        রিভিউ লিংক
                                    </a>
                                @endif
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

            <ol class="carousel-indicators review-indicators">
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
@if($isFaqSectionActive)
<section class="section-space bg-white" id="faq-section">
    <div class="container">
        <h2 class="section-title">{{ $faqSectionTitle }}</h2>

        <div class="faq-wrapper" id="faqAccordion">
            @foreach($faqItems as $faq)
            @php
            $question = is_array($faq)
                ? ($faq['question'] ?? $faq['title'] ?? '')
                : ($faq->question ?? $faq->title ?? '');

            $answer = is_array($faq)
                ? ($faq['answer'] ?? $faq['description'] ?? '')
                : ($faq->answer ?? $faq->description ?? '');

            $faqId = is_array($faq)
                ? ($faq['id'] ?? 'item_' . $loop->index)
                : ($faq->id ?? 'item_' . $loop->index);
            @endphp

            <div class="faq-item">
                <button class="faq-button collapsed" type="button" data-toggle="collapse" data-target="#faq_{{ $faqId }}"
                    aria-expanded="false">
                    <span>{{ $question }}</span>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div id="faq_{{ $faqId }}" class="collapse"
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
@if($isOrderSectionActive)
<section class="order-section" id="order-section">
    <div class="container">
        <h2 class="section-title">{{ $orderSectionTitle }}</h2>

        <form action="{{ $campaign->order_submit_url }}" method="POST"
            class="checkout-form order-panel" id="campaignOrderForm">
            @csrf
            <input type="hidden" name="order_form_token" value="{{ $orderFormToken ?? '' }}">
            <input type="hidden" name="device_identifier" id="deviceIdentifier" value="">

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

                        <div class="order-product-card" role="button" tabindex="0" aria-pressed="false"
                            aria-label="Select {{ $product->name }}"
                            data-id="{{ $product->id }}" data-name="{{ $product->name }}"
                            data-price="{{ $unitPrice }}" data-weight="{{ $weight }}" data-image="{{ $productImage }}"
                            data-free-delivery="{{ $product->is_free_delivery ? 1 : 0 }}">
                            <img src="{{ $productImage }}" alt="{{ $product->name }}"
                                data-image-fallback="{{ $noImage }}">

                            <div class="flex-grow-1">
                                <h5>{{ $product->name }}</h5>

                                <p class="order-product-meta-line">
    <span class="order-product-price">৳ {{ number_format($unitPrice) }}</span>

    @if($weight)
        <span class="order-product-weight">{{ $weight }}</span>
    @endif
</p>

@if($product->is_free_delivery)
    <span class="badge badge-success order-free-delivery-badge mt-1">ফ্রি ডেলিভারি</span>
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
                            <input type="text"
                                   name="phone"
                                   id="customerPhone"
                                   value="{{ old('phone') }}"
                                   class="form-control"
                                   inputmode="numeric"
                                   autocomplete="tel"
                                   minlength="11"
                                   maxlength="11"
                                   pattern="01[0-9]{9}"
                                   title="মোবাইল নাম্বার অবশ্যই ১১ ডিজিট হতে হবে এবং 01 দিয়ে শুরু হতে হবে (+88 ছাড়া)"
                                   placeholder="01XXXXXXXXX"
                                   required>
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
                                @forelse($shippingCharges as $shippingCharge)
                                    @php
                                        $shippingValue = (string) $shippingCharge->id;
                                        $shippingTitle = trim((string) $shippingCharge->area_name);
                                        $shippingAmount = (int) $shippingCharge->delivery_charge;
                                    @endphp

                                    <label class="delivery-area-card {{ $loop->first ? 'active' : '' }}">
                                        <input type="radio"
                                               name="delivery_area"
                                               value="{{ $shippingValue }}"
                                               data-charge="{{ $shippingAmount }}"
                                               @checked($loop->first)>
                                        <span class="delivery-area-title">{{ $shippingTitle }}</span>
                                        <span class="delivery-area-charge">৳{{ number_format($shippingAmount) }}</span>
                                    </label>
                                @empty
                                    <div class="alert alert-warning mb-0 w-100" id="noDeliveryAreaConfigured">
                                        এই campaign-এর জন্য কোনো active delivery area configure করা নেই।
                                    </div>
                                @endforelse
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
                            <strong id="summaryDeliveryCharge">৳{{ number_format($defaultShippingCharge) }}</strong>
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


{{-- Order Tracking --}}
@if($isOrderTrackingSectionActive)
<section class="order-tracking-section" id="order-track-section">
    <div class="container">
        <div class="order-tracking-box">
            <h2 class="section-title">অর্ডার ট্র্যাক করুন</h2>
            <p class="order-tracking-subtitle">অর্ডারে ব্যবহার করা ১১ সংখ্যার ফোন নম্বরটি লিখুন।</p>

            <form action="{{ route('order.track.search') }}" method="POST" id="orderTrackingForm">
                @csrf

                <div class="d-flex order-tracking-form-row">
                    <input type="tel"
                           name="tracking_phone"
                           id="trackingPhone"
                           value="{{ old('tracking_phone', $trackingSearchedPhone) }}"
                           class="form-control order-tracking-input mr-sm-2 @error('tracking_phone') is-invalid @enderror"
                           placeholder="01XXXXXXXXX"
                           maxlength="11"
                           inputmode="numeric"
                           pattern="01[0-9]{9}"
                           autocomplete="off"
                           required>

                    <button type="submit"
                            class="btn btn-success order-tracking-button"
                            id="orderTrackingSubmit">
                        <i class="fas fa-search mr-1"></i>
                        <span>Track Order</span>
                    </button>
                </div>

                <div id="orderTrackingError" class="text-danger small mt-2 @unless($errors->has('tracking_phone')) d-none @endunless">
                    @error('tracking_phone')
                        {{ $message }}
                    @enderror
                </div>
            </form>

            <div id="orderTrackingResults">
                @include('frontend.partials.order-tracking-results', [
                    'trackingOrders' => $trackingOrders,
                    'trackingSearchedPhone' => $trackingSearchedPhone,
                ])
            </div>
        </div>
    </div>
</section>
@endif

{{-- Help --}}
@if($isHelpSectionActive)
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
@endif

@endsection

@push('js')
@php
    $sfaHomeClientConfig = [
        'noImage' => $noImage,
        'heroVideoAutoplay' => (bool) $heroVideoAutoplay,
        'heroVideoMuted' => (bool) $heroVideoMuted,
        'deliveryCharges' => $shippingCharges->mapWithKeys(function ($charge) {
            return [(string) $charge->id => (int) $charge->delivery_charge];
        })->all(),
        'campaignId' => (string) ($campaign?->id ?? 'home'),
        'blockedOrderMessage' => session('blocked_order_error'),
    ];
@endphp
<div id="sfaHomeConfig" hidden data-config="{{ json_encode($sfaHomeClientConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"></div>
<script src="{{ asset('frontend/js/home.js') }}?v=20261024-frontend2" defer></script>
@endpush
@endif