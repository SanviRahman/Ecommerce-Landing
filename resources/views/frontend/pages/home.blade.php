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
            $shippingCharges = \App\Models\ShippingCharge::query()
                ->active()
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            $shippingCharges = collect();
        }
    }

    if ($shippingCharges->isEmpty()) {
        $shippingCharges = collect([
            (object) ['id' => 'inside_dhaka', 'area_name' => 'ঢাকার ভিতরে', 'delivery_charge' => 70, 'status' => true],
            (object) ['id' => 'outside_dhaka', 'area_name' => 'ঢাকার বাইরে', 'delivery_charge' => 130, 'status' => true],
        ]);
    }

    $defaultShippingCharge = (int) ($shippingCharges->first()->delivery_charge ?? 0);

    $defaultCategoryId = 'all';
    $defaultBrandId = 'all';

    $heroImage = $campaign
        ? ($campaign->image_three_url ?: ($campaign->banner_image_url ?: $noImage))
        : $noImage;

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

    if ($heroEmbedUrl) {
        if (\Illuminate\Support\Str::contains($heroEmbedUrl, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'])) {
            $videoEmbedUrl = $youtubeEmbedFromUrl($heroEmbedUrl);
        } elseif (\Illuminate\Support\Str::contains($heroEmbedUrl, ['facebook.com/plugins/video'])) {
            $videoEmbedUrl = $heroEmbedUrl;
        } elseif (\Illuminate\Support\Str::contains($heroEmbedUrl, ['facebook.com', 'fb.watch'])) {
            $videoEmbedUrl = 'https://www.facebook.com/plugins/video.php?href=' . urlencode($heroEmbedUrl) . '&show_text=false&width=560';
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
            $videoEmbedUrl = $uploadedCampaignVideoUrl;
        } elseif (\Illuminate\Support\Str::contains($uploadedCampaignVideoUrl, ['facebook.com', 'fb.watch'])) {
            $videoEmbedUrl = 'https://www.facebook.com/plugins/video.php?href=' . urlencode($uploadedCampaignVideoUrl) . '&show_text=false&width=560';
        } else {
            $fallbackVideoFileUrl = \Illuminate\Support\Str::startsWith($uploadedCampaignVideoUrl, ['http://', 'https://', '/'])
                ? $uploadedCampaignVideoUrl
                : \Illuminate\Support\Facades\Storage::url($uploadedCampaignVideoUrl);

            $videoFileUrl = $fallbackVideoFileUrl;
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
    $isProductSectionActive = (bool) ($campaign?->product_section_status ?? true);
    $isComparisonSectionActive = (bool) ($campaign?->comparison_section_status ?? true) && $comparisonMaxRows > 0;
    $isServiceSectionActive = (bool) ($campaign?->service_section_status ?? true);
    $isGallerySectionActive = (bool) ($campaign?->gallery_section_status ?? true) && $campaignGalleryImages->isNotEmpty();
    $isReviewSectionActive = (bool) ($campaign?->review_section_status ?? true) && $reviewItems->isNotEmpty();
    $isFaqSectionActive = (bool) ($campaign?->faq_section_status ?? true) && $faqItems->isNotEmpty();
    $isHelpSectionActive = (bool) ($campaign?->help_section_status ?? true);
    $isOrderSectionActive = (bool) ($campaign?->order_section_status ?? true) && (bool) $campaign;

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

/* Site logo size fix */
.site-logo-img,
.frontend-site-logo,
.navbar-brand img,
.site-header-logo img {
    width: 280px !important;
    height: 80px !important;
    object-fit: contain !important;
    max-width: 280px !important;
}

/* Hero slider */
.hero-slider-box,
.hero-media-fallback-box {
    position: relative;
    overflow: hidden;
    max-width: 445px;
    margin-left: auto;
    border-radius: 20px;
    background: var(--front-soft);
    box-shadow: var(--front-shadow);
}

.hero-slider-box .carousel,
.hero-slider-box .carousel-inner,
.hero-slider-box .carousel-item {
    height: 500px;
}

.hero-slider-box img,
.hero-media-fallback-box img {
    width: 100%;
    height: 500px;
    object-fit: cover;
    display: block;
}

.hero-slider-box .carousel-indicators {
    bottom: 14px;
}

.hero-slider-box .carousel-indicators li {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    border: 0;
    background: rgba(255, 255, 255, 0.75);
    opacity: 1;
}

.hero-slider-box .carousel-indicators li.active {
    width: 28px;
    background: var(--front-green);
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
    height: 600px;
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

.review-social-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 4px;
    color: var(--front-primary);
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
}

.review-social-link:hover {
    color: var(--front-secondary);
    text-decoration: none;
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

.order-product-meta-line {
    min-width: 0;
}

.order-free-delivery-badge {
    display: inline-block;
    max-width: 100%;
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
        padding: 18px 0 44px;
    }

    .hero-section .row {
        display: flex;
        flex-direction: column;
    }

    .hero-section .hero-video-col {
        order: 1;
        margin-bottom: 28px;
    }

    .hero-section .hero-content {
        order: 2;
        display: block !important;
        text-align: center;
    }

    .hero-title {
        font-size: 34px;
        margin-bottom: 18px;
    }

    .hero-text {
        font-size: 17px;
        line-height: 1.8;
    }

    .hero-check-list {
        grid-template-columns: repeat(3, 1fr);
        gap: 12px 18px;
        max-width: 650px;
        margin: 28px auto;
        text-align: left;
    }

    .hero-actions {
        justify-content: center;
    }

    .hero-section .hero-video-col {
        width: 100%;
        max-width: 100%;
        display: flex;
        justify-content: center;
    }

    .hero-video-box,
    .hero-slider-box,
    .hero-media-fallback-box {
        width: 100%;
        max-width: 445px;
        margin-left: auto;
        margin-right: auto;
        border-radius: 20px;
    }

    .hero-slider-box .carousel,
    .hero-slider-box .carousel-inner,
    .hero-slider-box .carousel-item,
    .hero-video-box video,
    .hero-video-box iframe,
    .hero-slider-box img,
    .hero-media-fallback-box img {
        height: 490px;
        border-radius: 20px;
    }

    .section-space {
        padding: 58px 0;
    }

    .gallery-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .review-slide-card {
        width: 520px;
    }

    .difference-mobile-images {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .difference-mobile-images img {
        width: 100%;
        height: 155px;
        object-fit: cover;
        border-radius: 14px;
        box-shadow: var(--front-shadow);
        background: var(--front-soft);
    }

    .difference-desktop-image {
        display: none !important;
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

    .order-products-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .order-product-card {
        min-height: 105px;
        align-items: flex-start;
        padding: 9px;
        overflow: hidden;
    }

    .order-product-card img {
        width: 58px;
        height: 58px;
        margin-right: 8px;
        border-radius: 8px;
    }

    .order-product-card h5 {
        font-size: 14px;
        line-height: 1.22;
        margin-bottom: 4px;
        word-break: break-word;
    }

    .order-product-card p {
        display: block;
        font-size: 12px;
        line-height: 1.3;
    }

    .order-product-price,
    .order-product-weight {
        display: block;
        white-space: normal;
    }

    .order-product-card .badge {
        font-size: 10px;
        line-height: 1.2;
        padding: 3px 5px;
        white-space: normal;
    }

    .selected-check {
        right: 8px;
        top: 8px;
        z-index: 5;
    }

    .order-summary-card {
        position: static;
        top: auto;
    }
}

@media (max-width: 575px) {
    .site-logo-img,
    .frontend-site-logo,
    .navbar-brand img,
    .site-header-logo img {
        width: 180px !important;
        height: auto !important;
        max-width: 180px !important;
    }

    .container {
        padding-left: 16px;
        padding-right: 16px;
    }

    .hero-section {
        padding-top: 12px;
    }

    .hero-slider-box,
    .hero-media-fallback-box,
    .hero-video-box {
        max-width: 100%;
    }

    .hero-slider-box .carousel,
    .hero-slider-box .carousel-inner,
    .hero-slider-box .carousel-item,
    .hero-video-box video,
    .hero-video-box iframe,
    .hero-slider-box img,
    .hero-media-fallback-box img {
        height: 325px;
    }

    .hero-title {
        font-size: 30px;
    }

    .hero-text {
        font-size: 16px;
    }

    .hero-check-list {
        grid-template-columns: repeat(2, 1fr);
        gap: 11px 14px;
    }

    .hero-check-item {
        font-size: 14px;
    }

    .rating-stars {
        font-size: 20px;
    }

    .hero-actions .btn {
        padding: 12px 18px;
    }

    .side-action-btn,
    .help-icon-btn {
        width: 44px;
        height: 44px;
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

    .order-products-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .order-product-card {
        min-height: 118px;
        padding: 8px;
    }

    .order-product-card img {
        width: 52px;
        height: 52px;
        margin-right: 7px;
    }

    .order-product-card h5 {
        font-size: 13px;
        line-height: 1.2;
    }

    .order-product-card p {
        font-size: 11px;
    }

    .difference-mobile-images img {
        height: 125px;
    }
}

@media (max-width: 380px) {
    .order-products-grid {
        grid-template-columns: 1fr;
    }

    .order-product-card {
        min-height: auto;
    }

    .order-product-card h5 {
        font-size: 15px;
    }

    .order-product-card p {
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }

    .order-product-price,
    .order-product-weight {
        display: inline-block;
    }
}

/* Order form beautiful popup */
.order-popup-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15, 23, 42, 0.56);
    backdrop-filter: blur(4px);
}

.order-popup-overlay.show {
    display: flex;
}

.order-popup-box {
    width: min(420px, 100%);
    border-radius: 22px;
    background: #ffffff;
    box-shadow: 0 28px 80px rgba(15, 23, 42, 0.24);
    overflow: hidden;
    transform: translateY(14px) scale(0.96);
    opacity: 0;
    transition: 0.22s ease;
}

.order-popup-overlay.show .order-popup-box {
    transform: translateY(0) scale(1);
    opacity: 1;
}

.order-popup-top {
    padding: 30px 24px 18px;
    text-align: center;
}

.order-popup-icon {
    width: 74px;
    height: 74px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fff7ed;
    color: #f97316;
    font-size: 34px;
    margin-bottom: 16px;
    box-shadow: 0 12px 32px rgba(249, 115, 22, 0.16);
}

.order-popup-title {
    color: #1e293b;
    font-size: 24px;
    font-weight: 900;
    margin-bottom: 8px;
}

.order-popup-message {
    color: #64748b;
    font-size: 16px;
    line-height: 1.75;
    margin: 0;
}

.order-popup-actions {
    display: flex;
    gap: 10px;
    padding: 18px 24px 24px;
}

.order-popup-btn {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 12px;
    background: var(--front-green);
    color: #ffffff;
    font-size: 16px;
    font-weight: 900;
    cursor: pointer;
    transition: 0.2s ease;
}

.order-popup-btn:hover,
.order-popup-btn:focus {
    background: var(--front-green-dark);
    outline: none;
    transform: translateY(-1px);
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
                            <iframe src="{{ $videoEmbedUrl }}"
                                    title="{{ $heroTitle }}"
                                    allowfullscreen
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                            </iframe>
                        @elseif($videoFileUrl)
                            <video controls preload="metadata" poster="{{ $heroVideoPoster }}">
                                <source src="{{ $videoFileUrl }}" type="video/mp4">
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

        @if($products->isNotEmpty())
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
                     onerror="this.onerror=null;this.src='{{ $noImage }}';">
            </div>

            <div class="col-lg-6">
                <div class="difference-mobile-images d-none">
                    <img src="{{ $campaign?->image_one_url ?: $noImage }}"
                         alt="{{ $comparisonLeftTitle }}"
                         onerror="this.onerror=null;this.src='{{ $noImage }}';">

                    <img src="{{ $campaign?->image_two_url ?: $noImage }}"
                         alt="{{ $comparisonRightTitle }}"
                         onerror="this.onerror=null;this.src='{{ $noImage }}';">
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
                     onerror="this.onerror=null;this.src='{{ $noImage }}';">
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
                onerror="this.onerror=null;this.src='{{ $noImage }}';">
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
                             onerror="this.onerror=null;this.src='{{ $noImage }}';">
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
                                onerror="this.onerror=null;this.src='{{ $noImage }}';">

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

        <form action="{{ route('campaign.order.store', $campaign->slug) }}" method="POST"
            class="checkout-form order-panel" id="campaignOrderForm">
            @csrf
            <input type="hidden" name="order_form_token" value="{{ $orderFormToken ?? '' }}">

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
                                @foreach($shippingCharges as $shippingCharge)
                                    @php
                                        $shippingValue = (string) ($shippingCharge->id ?? '');
                                        $shippingTitle = $shippingCharge->area_name ?? '';
                                        $shippingAmount = (int) ($shippingCharge->delivery_charge ?? 0);
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
                                @endforeach
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
<script>
$(document).ready(function() {
    const noImage = @json($noImage);

    const deliveryCharges = @json($shippingCharges->mapWithKeys(function ($charge) {
        return [(string) $charge->id => (int) $charge->delivery_charge];
    }));

    const defaultDeliveryArea = Object.keys(deliveryCharges)[0] || '';

    let selectedCategory = 'all';
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
            || defaultDeliveryArea;

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

            const orderSection = $('#order-section');

            if (orderSection.length) {
                $('html, body').animate({
                    scrollTop: orderSection.offset().top - 80
                }, 500);
            }
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

    const orderResetStorageKey = 'campaign_order_reset_{{ $campaign?->id ?? 'home' }}';
    const shouldResetFromPreviousOrder = sessionStorage.getItem(orderResetStorageKey) === '1';

    function resetOrderFormAfterSuccess() {
        selectedProducts = {};

        const form = $('#campaignOrderForm');

        if (form.length && form[0]) {
            form[0].reset();
        }

        $('.order-product-card').removeClass('active');
        $('#hiddenProductInputs').empty();

        const firstDeliveryInput = $('input[name="delivery_area"]').first();

        if (firstDeliveryInput.length) {
            $('input[name="delivery_area"]').prop('checked', false);
            $('.delivery-area-card').removeClass('active');

            firstDeliveryInput.prop('checked', true);
            firstDeliveryInput.closest('.delivery-area-card').addClass('active');
        }

        $('#campaignOrderForm').find('[type="submit"]')
            .prop('disabled', false)
            .removeClass('disabled');

        renderSummary();
    }

    window.addEventListener('pageshow', function(event) {
        if (event.persisted || sessionStorage.getItem(orderResetStorageKey) === '1') {
            resetOrderFormAfterSuccess();
            sessionStorage.removeItem(orderResetStorageKey);
        }
    });

    function showOrderWarningPopup(message) {
        const popup = $('#orderWarningPopup');

        if (!popup.length) {
            return;
        }

        $('#orderWarningPopupMessage').text(message || 'দয়া করে কমপক্ষে একটি product select করুন।');
        popup.addClass('show').attr('aria-hidden', 'false');

        setTimeout(function () {
            $('#orderWarningPopupClose').trigger('focus');
        }, 80);
    }

    function hideOrderWarningPopup() {
        $('#orderWarningPopup').removeClass('show').attr('aria-hidden', 'true');
    }

    $(document).on('click', '#orderWarningPopupClose', function() {
        hideOrderWarningPopup();
    });

    $(document).on('click', '#orderWarningPopup', function(e) {
        if (e.target === this) {
            hideOrderWarningPopup();
        }
    });

    $(document).on('keyup', function(e) {
        if (e.key === 'Escape') {
            hideOrderWarningPopup();
        }
    });

    function normalizeCustomerPhone(value) {
        return String(value || '').replace(/\D/g, '').slice(0, 11);
    }

    function isValidCustomerPhone(value) {
        return /^01[0-9]{9}$/.test(String(value || ''));
    }

    $(document).on('input', '#customerPhone', function() {
        $(this).val(normalizeCustomerPhone($(this).val()));
    });

    $('#campaignOrderForm').on('submit', function(e) {
        const customerPhone = normalizeCustomerPhone($('#customerPhone').val());

        $('#customerPhone').val(customerPhone);

        if (!isValidCustomerPhone(customerPhone)) {
            e.preventDefault();
            showOrderWarningPopup('মোবাইল নাম্বার অবশ্যই ১১ ডিজিট হতে হবে এবং 01 দিয়ে শুরু হতে হবে (+88 ছাড়া)।');
            $('#customerPhone').trigger('focus');
            return false;
        }

        if (!Object.keys(selectedProducts).length) {
            e.preventDefault();
            showOrderWarningPopup('দয়া করে কমপক্ষে একটি product select করুন।');
            return false;
        }

        sessionStorage.setItem(orderResetStorageKey, '1');

        $(this).find('[type="submit"]')
            .prop('disabled', true)
            .addClass('disabled');

        trackBeginCheckout();

        return true;
    });

    if (shouldResetFromPreviousOrder) {
        resetOrderFormAfterSuccess();
        sessionStorage.removeItem(orderResetStorageKey);
    } else {
        suppressTracking = true;
        $('.order-product-card').first().trigger('click');
        suppressTracking = false;
    }

    filterProducts();
    trackViewContentOnce();
});
</script>
@endpush
@endif

