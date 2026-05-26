@php
    $campaign = $campaign ?? null;
    $isEdit = $isEdit ?? false;

    $categories = $categories ?? collect();
    $brands = $brands ?? collect();
    $products = $products ?? collect();

    $selectedCategories = old('categories', $selectedCategories ?? []);
    $selectedBrands = old('brands', $selectedBrands ?? []);
    $selectedProducts = old('products', $selectedProducts ?? []);

    $defaultBenefits = [
        'গাছের মাছ',
        'ইলিশের মাথা',
        'মাছ',
        'দেশী',
        'খাসির মাথা',
        'মুরগী মট',
        'সরিষা',
        'কালিজিরা',
        'চটপটি',
    ];

    $benefits = old('benefits_text', $campaign->benefits_text ?? $defaultBenefits);

    $comparison = old('comparison_text', $campaign->comparison_text ?? [
        'left_title' => 'গাছ চুইঝাল',
        'right_title' => 'এটা চুইঝাল',
        'left' => [
            'চুইঝাল গাছের কাণ্ডকে গাছ চুইঝাল বলা হয়।',
            'গাছ চুইঝাল সাধারণত রান্নায় সহজে গলে যায়।',
            'রান্নায় ঝাঁজ ও ঘ্রাণ বাড়াতে ব্যবহার করা হয়।',
            'সাধারণত বড় পরিমাণে ব্যবহার করা হয়।',
            'এটি রান্নার স্বাদকে আলাদা করে তোলে।',
        ],
        'right' => [
            'চুইঝাল গাছের গোড়া এবং গোড়া সংলগ্ন অংশকে এটা চুইঝাল বলা হয়।',
            'এটা চুইঝাল ফাইবার কম থাকায় রান্নায় ভালো ফ্লেভার দেয়।',
            'মাছ, ডাল ও তরকারিতে ব্যবহার করা যায়।',
            'মসলা হিসেবে স্বাদ ও ঘ্রাণ বাড়াতে ব্যবহার করা হয়।',
            'এটি সাধারণ খাবারকেও সুস্বাদু করে তোলে।',
        ],
    ]);

    $sectionTitles = old('section_titles', $campaign->section_titles ?? [
        'category_title' => 'ক্যাটাগরি সমূহ',
        'brand_title' => 'ব্র্যান্ড সমূহ',
        'product_title' => 'আমাদের প্রোডাক্ট',
        'category_filter_title' => 'ক্যাটাগরি দিয়ে ফিল্টার',
        'brand_filter_title' => 'ব্র্যান্ড দিয়ে ফিল্টার',
        'comparison_title' => 'চুইঝালের পার্থক্যসমূহ',
        'service_title' => 'কেন আমরাই সেরা',
        'review_title' => 'কাস্টমার রিভিউ',
        'faq_title' => 'সচরাচর জিজ্ঞাস্য প্রশ্নাবলি',
        'gallery_title' => 'প্রোডাক্ট গ্যালারি',
        'order_title' => 'অর্ডার করুন এখনই',
        'hero_star_count' => '5',
        'hero_rating_text' => '৩০,০০০ হাজারও অধিক গ্রাহকের কাছে<br>আমরা হয়েছি জনপ্রিয়',
    ]);

    $serviceItems = old('service_items', $campaign->service_items ?? [
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
    ]);

    $helpContent = old('help_content', $campaign->help_content ?? [
        'title' => 'সাহায্য প্রয়োজন?',
        'description' => 'যেকোনো জিজ্ঞাসা ও অর্ডারজনিত সমস্যায় কল করুন আমাদের হেল্পলাইনে অথবা নক করুন আমাদের হোয়াটসঅ্যাপ বা ফেসবুক পেজে। আমরা আছি সকাল ১০ টা থেকে রাত ৮ টা পর্যন্ত আপনার সেবায়।',
        'button_text' => 'হেল্পলাইন',
    ]);



    $faqSource = old('campaign_faqs');

    if ($faqSource === null) {
        $faqSource = $campaignFaqs ?? ($campaign?->faqs ?? collect());
    }

    $campaignFaqRows = collect($faqSource)->map(function ($faq, $index) {
        if (is_array($faq)) {
            return [
                'id' => $faq['id'] ?? null,
                'question' => $faq['question'] ?? '',
                'answer' => $faq['answer'] ?? '',
                'sort_order' => $faq['sort_order'] ?? $index,
                'status' => array_key_exists('status', $faq) ? (bool) $faq['status'] : true,
            ];
        }

        return [
            'id' => $faq->id ?? null,
            'question' => $faq->question ?? '',
            'answer' => $faq->answer ?? '',
            'sort_order' => $faq->sort_order ?? $index,
            'status' => (bool) ($faq->status ?? true),
        ];
    })->values();

    if ($campaignFaqRows->isEmpty()) {
        $campaignFaqRows = collect([
            ['id' => null, 'question' => '', 'answer' => '', 'sort_order' => 0, 'status' => true],
        ]);
    }

    $reviewSource = old('campaign_reviews');

    if ($reviewSource === null) {
        $reviewSource = $campaignReviews ?? ($campaign?->reviews ?? collect());
    }

    $campaignReviewRows = collect($reviewSource)->map(function ($review) {
        if (is_array($review)) {
            return [
                'id' => $review['id'] ?? null,
                'customer_name' => $review['customer_name'] ?? '',
                'location' => $review['location'] ?? '',
                'rating' => $review['rating'] ?? 5,
                'review_text' => $review['review_text'] ?? '',
                'social_link' => $review['social_link'] ?? '',
                'status' => array_key_exists('status', $review) ? (bool) $review['status'] : true,
                'image_url' => null,
            ];
        }

        return [
            'id' => $review->id ?? null,
            'customer_name' => $review->customer_name ?? '',
            'location' => $review->location ?? '',
            'rating' => $review->rating ?? 5,
            'review_text' => $review->review_text ?? '',
            'social_link' => $review->social_link ?? '',
            'status' => (bool) ($review->status ?? true),
            'image_url' => $review->customer_image ?? null,
        ];
    })->values();

    if ($campaignReviewRows->isEmpty()) {
        $campaignReviewRows = collect([
            [
                'id' => null,
                'customer_name' => '',
                'location' => '',
                'rating' => 5,
                'review_text' => '',
                'social_link' => '',
                'status' => true,
                'image_url' => null,
            ],
        ]);
    }

    $mediaFields = [
        'banner_image' => [
            'label' => 'Banner Image',
            'type' => 'image',
            'hint' => 'Recommended size: 800x600px',
        ],
        'image_one' => [
            'label' => 'Image One',
            'type' => 'image',
            'hint' => 'Used in comparison left image',
        ],
        'image_two' => [
            'label' => 'Image Two',
            'type' => 'image',
            'hint' => 'Used in comparison right image',
        ],
        'image_three' => [
            'label' => 'Image Three',
            'type' => 'image',
            'hint' => 'Used in campaign sections',
        ],
        'review_image' => [
            'label' => 'Review Image',
            'type' => 'image',
            'hint' => 'Used in review section',
        ],
        'campaign_video' => [
            'label' => 'Campaign Video',
            'type' => 'video',
            'hint' => 'Allowed: mp4, webm, ogg. Maximum size: 50MB.',
        ],
    ];

    $sectionSwitch = function ($field, $label) use ($campaign) {
        $checked = old($field, $campaign->{$field} ?? true);

        return '
            <div class="custom-control custom-switch section-status-switch">
                <input type="checkbox"
                       name="' . e($field) . '"
                       value="1"
                       class="custom-control-input"
                       id="' . e($field) . '"
                       ' . ($checked ? 'checked' : '') . '>

                <label class="custom-control-label font-weight-bold" for="' . e($field) . '">
                    ' . e($label) . '
                </label>
            </div>
        ';
    };
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data" id="campaignForm">
    @csrf

    @if($isEdit)
        @method('PUT')
    @endif

    {{-- Basic Information --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-bullhorn text-primary mr-1"></i>
                Basic Information
            </h5>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="font-weight-bold">
                        Campaign Title <span class="text-danger">*</span>
                    </label>

                    <input type="text"
                           name="title"
                           value="{{ old('title', $campaign->title ?? '') }}"
                           class="form-control @error('title') is-invalid @enderror"
                           placeholder="Campaign title"
                           required>

                    @error('title')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Slug</label>

                    <input type="text"
                           name="slug"
                           value="{{ old('slug', $campaign->slug ?? '') }}"
                           class="form-control @error('slug') is-invalid @enderror"
                           placeholder="Auto generated if empty">

                    @error('slug')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-md-12 mb-3">
                    <label class="font-weight-bold">Short Description</label>

                    <textarea name="short_description"
                              rows="3"
                              class="form-control @error('short_description') is-invalid @enderror"
                              placeholder="Short campaign description">{{ old('short_description', $campaign->short_description ?? '') }}</textarea>

                    @error('short_description')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Button Text</label>

                    <input type="text"
                           name="button_text"
                           value="{{ old('button_text', $campaign->button_text ?? 'অর্ডার করুন') }}"
                           class="form-control @error('button_text') is-invalid @enderror"
                           placeholder="Button text">

                    @error('button_text')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Hero WhatsApp Number / Link</label>

                    <input type="text"
                           name="hero_whatsapp"
                           value="{{ old('hero_whatsapp', $campaign->hero_whatsapp ?? '') }}"
                           class="form-control @error('hero_whatsapp') is-invalid @enderror"
                           placeholder="Example: 01700000000 or https://wa.me/8801700000000">

                    <small class="text-muted">
                        Campaign-wise WhatsApp button এখানে থেকে dynamic হবে।
                    </small>

                    @error('hero_whatsapp')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Hero Phone Number</label>

                    <input type="text"
                           name="hero_phone"
                           value="{{ old('hero_phone', $campaign->hero_phone ?? '') }}"
                           class="form-control @error('hero_phone') is-invalid @enderror"
                           placeholder="Example: 01700000000">

                    <small class="text-muted">
                        Campaign-wise call button এখানে থেকে dynamic হবে।
                    </small>

                    @error('hero_phone')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Order Form Title</label>

                    <input type="text"
                           name="order_form_title"
                           value="{{ old('order_form_title', $campaign->order_form_title ?? 'ডেলিভারি এড্রেস') }}"
                           class="form-control @error('order_form_title') is-invalid @enderror"
                           placeholder="Order form title">

                    @error('order_form_title')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Order Form Subtitle</label>

                    <input type="text"
                           name="order_form_subtitle"
                           value="{{ old('order_form_subtitle', $campaign->order_form_subtitle ?? '') }}"
                           class="form-control @error('order_form_subtitle') is-invalid @enderror"
                           placeholder="Order form subtitle">

                    @error('order_form_subtitle')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Hero Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-home text-success mr-1"></i>
                    Hero Section
                </h5>

                <small class="text-muted">
                    1st Embed Video, 2nd Multiple Image Slider, 3rd Uploaded Video. Frontend এই priority follow করবে।
                </small>
            </div>

            {!! $sectionSwitch('hero_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            {{-- 1st Priority: Embed Video --}}
            <div class="form-group mb-4">
                <label class="font-weight-bold">1. Embed Video URL</label>

                <input type="url"
                       name="embed_video_url"
                       value="{{ old('embed_video_url', $campaign->embed_video_url ?? '') }}"
                       class="form-control @error('embed_video_url') is-invalid @enderror"
                       placeholder="YouTube/Facebook video URL. Example: https://youtu.be/VIDEO_ID">

                <small class="text-muted">
                    Embed URL থাকলে frontend hero media হিসেবে প্রথমে এটিই show হবে।
                </small>

                @error('embed_video_url')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            </div>

            {{-- 2nd Priority: Multiple Hero Images --}}
            <div class="border rounded p-3 bg-light mb-4">
                <label class="font-weight-bold d-block">
                    <i class="fas fa-images text-success mr-1"></i>
                    2. Hero Multiple Images / Auto Slider
                </label>

                <input type="file"
                       name="hero_slider_images[]"
                       id="heroSliderImagesInput"
                       class="form-control-file @error('hero_slider_images') is-invalid @enderror @error('hero_slider_images.*') is-invalid @enderror"
                       accept="image/*"
                       multiple>

                <small class="text-muted d-block mt-1">
                    একসাথে multiple image select করা যাবে, preview হবে, selected image instant remove করা যাবে।
                    Embed video না থাকলে frontend hero section-এ এগুলো auto slider with indicators হিসেবে show হবে।
                </small>

                @error('hero_slider_images')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror

                @error('hero_slider_images.*')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror

                <div class="row mt-3" id="heroSliderImagesSelectedPreview"></div>

                @if($isEdit && $campaign && method_exists($campaign, 'getMedia'))
                    <div class="row mt-3" id="heroSliderImagesExistingPreview">
                        @foreach($campaign->getMedia('hero_slider_images') as $media)
                            <div class="col-md-3 col-lg-2 mb-3" id="hero-slider-media-box-{{ $media->id }}">
                                <div class="campaign-gallery-preview-item border rounded bg-white p-1">
                                    <img src="{{ $media->getUrl() }}"
                                         alt="Hero Slider Image"
                                         class="campaign-gallery-preview-img">

                                    <button type="button"
                                            class="btn btn-xs btn-danger btn-block mt-1 hero-slider-delete-btn"
                                            data-id="{{ $media->id }}"
                                            data-url="{{ route('admin.campaigns.delete_media', $media->id) }}">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 3rd Priority: Uploaded Video --}}
            @php
                $field = 'campaign_video';
                $mediaConfig = $mediaFields[$field];
                $existingMedia = $campaign ? $campaign->getFirstMedia($field) : null;
                $existingUrl = $existingMedia ? $existingMedia->getUrl() : null;
            @endphp

            <div class="form-group mb-4">
                <label class="font-weight-bold">3. Campaign Video Upload</label>

                <input type="file"
                       name="{{ $field }}"
                       class="form-control-file campaign-media-input @error($field) is-invalid @enderror"
                       data-preview="#preview_{{ $field }}"
                       data-type="{{ $mediaConfig['type'] }}"
                       accept="video/mp4,video/webm,video/ogg">

                <small class="text-muted d-block mt-1">
                    Embed video এবং hero multiple image না থাকলে এই uploaded video frontend hero media হিসেবে show হবে।
                </small>

                @error($field)
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror

                <div class="campaign-preview-box mt-2" id="preview_{{ $field }}">
                    @if($existingUrl)
                        <div class="existing-media-box position-relative d-inline-block">
                            <video src="{{ $existingUrl }}" controls class="campaign-video-preview"></video>

                            <button type="button"
                                    class="btn btn-sm btn-danger campaign-media-delete-btn"
                                    data-url="{{ route('admin.campaigns.delete_media', $existingMedia->id) }}"
                                    data-target="#preview_{{ $field }}"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    @else
                        <div class="text-muted small border rounded p-3 bg-light">No file selected</div>
                    @endif
                </div>
            </div>

            {{-- Dynamic Star / Rating Text --}}
            <div class="border rounded p-3 bg-white">
                <h6 class="font-weight-bold mb-3">
                    <i class="fas fa-star text-warning mr-1"></i>
                    Hero Star & Rating Text
                </h6>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Star Count</label>

                        <select name="section_titles[hero_star_count]" class="form-control">
                            @for($star = 1; $star <= 5; $star++)
                                <option value="{{ $star }}"
                                    @selected((string) old('section_titles.hero_star_count', $sectionTitles['hero_star_count'] ?? 5) === (string) $star)>
                                    {{ $star }} Star
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-8 mb-3">
                        <label class="font-weight-bold">Rating Text</label>

                        <textarea name="section_titles[hero_rating_text]"
                                  rows="3"
                                  class="form-control"
                                  placeholder="Example: ৩০,০০০ হাজারও অধিক গ্রাহকের কাছে&lt;br&gt;আমরা হয়েছি জনপ্রিয়">{{ old('section_titles.hero_rating_text', $sectionTitles['hero_rating_text'] ?? '৩০,০০০ হাজারও অধিক গ্রাহকের কাছে<br>আমরা হয়েছি জনপ্রিয়') }}</textarea>

                        <small class="text-muted">
                            Line break দিতে চাইলে &lt;br&gt; use করতে পারবেন।
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Hero Benefits Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-check-circle text-success mr-1"></i>
                    Hero Benefits Section
                </h5>

                <small class="text-muted">Frontend hero section-er benefit list ekhane theke dynamic hobe.</small>
            </div>

            {!! $sectionSwitch('benefits_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="row" id="benefits-wrapper">
                @foreach($benefits ?? [] as $benefit)
                    <div class="col-md-4 benefit-item mb-2">
                        <div class="input-group">
                            <input type="text"
                                   name="benefits_text[]"
                                   value="{{ $benefit }}"
                                   class="form-control"
                                   placeholder="Benefit text">

                            <div class="input-group-append">
                                <button type="button" class="btn btn-danger btn-remove-benefit">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnAddBenefit">
                <i class="fas fa-plus mr-1"></i> Add Benefit
            </button>

            @error('benefits_text')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- Category / Brand Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-layer-group text-primary mr-1"></i>
                    Category / Brand Section
                </h5>

                <small class="text-muted">Home page sequence অনুযায়ী category এবং brand section.</small>
            </div>

            {!! $sectionSwitch('category_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">Category Section Title</label>

                    <input type="text"
                           name="section_titles[category_title]"
                           value="{{ old('section_titles.category_title', $sectionTitles['category_title'] ?? 'ক্যাটাগরি সমূহ') }}"
                           class="form-control"
                           placeholder="Category Section Title">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">Brand Section Title</label>

                    <input type="text"
                           name="section_titles[brand_title]"
                           value="{{ old('section_titles.brand_title', $sectionTitles['brand_title'] ?? 'ব্র্যান্ড সমূহ') }}"
                           class="form-control"
                           placeholder="Brand Section Title">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">Categories</label>

                    <select name="categories[]"
                            class="form-control select2-categories @error('categories') is-invalid @enderror"
                            multiple>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(in_array($category->id, $selectedCategories))>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>

                    <small class="text-muted">
                        Search kore multiple category select koro. Jeta age select korbe frontend-e age show hobe.
                    </small>

                    @error('categories')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">Brands</label>

                    <select name="brands[]"
                            class="form-control select2-brands @error('brands') is-invalid @enderror"
                            multiple>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(in_array($brand->id, $selectedBrands))>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>

                    <small class="text-muted">
                        Search kore multiple brand select koro. Jeta age select korbe frontend-e age show hobe.
                    </small>

                    @error('brands')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Product Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-box-open text-primary mr-1"></i>
                    Product Section
                </h5>

                <small class="text-muted">Frontend product grid/order section-e sudhu selected products show hobe.</small>
            </div>

            {!! $sectionSwitch('product_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Product Section Title</label>

                    <input type="text"
                           name="section_titles[product_title]"
                           value="{{ old('section_titles.product_title', $sectionTitles['product_title'] ?? 'আমাদের প্রোডাক্ট') }}"
                           class="form-control"
                           placeholder="Product Section Title">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Category Filter Title</label>

                    <input type="text"
                           name="section_titles[category_filter_title]"
                           value="{{ old('section_titles.category_filter_title', $sectionTitles['category_filter_title'] ?? 'ক্যাটাগরি দিয়ে ফিল্টার') }}"
                           class="form-control"
                           placeholder="Category Filter Title">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Brand Filter Title</label>

                    <input type="text"
                           name="section_titles[brand_filter_title]"
                           value="{{ old('section_titles.brand_filter_title', $sectionTitles['brand_filter_title'] ?? 'ব্র্যান্ড দিয়ে ফিল্টার') }}"
                           class="form-control"
                           placeholder="Brand Filter Title">
                </div>
            </div>

            <div class="form-group mb-0">
                <label class="font-weight-bold">
                    Products <span class="text-danger">*</span>
                </label>

                <select name="products[]"
                        class="form-control select2-products @error('products') is-invalid @enderror"
                        multiple
                        required>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected(in_array($product->id, $selectedProducts))>
                            {{ $product->name }} — ৳{{ number_format($product->new_price ?? 0) }}
                        </option>
                    @endforeach
                </select>

                <small class="text-muted">
                    Search kore multiple product select koro. Frontend product grid/order section-e sudhu selected products show hobe.
                </small>

                @error('products')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- Difference / Comparison Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-balance-scale text-success mr-1"></i>
                    Comparison Section
                </h5>

                <small class="text-muted">Frontend-er comparison section ekhane theke dynamic hobe.</small>
            </div>

            {!! $sectionSwitch('comparison_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Comparison Section Title</label>

                <input type="text"
                       name="section_titles[comparison_title]"
                       value="{{ old('section_titles.comparison_title', $sectionTitles['comparison_title'] ?? 'চুইঝালের পার্থক্যসমূহ') }}"
                       class="form-control"
                       placeholder="Comparison Section Title">
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="font-weight-bold">Left Column Title</label>

                    <input type="text"
                           name="comparison_text[left_title]"
                           value="{{ old('comparison_text.left_title', $comparison['left_title'] ?? 'গাছ চুইঝাল') }}"
                           class="form-control"
                           placeholder="Example: গাছ চুইঝাল">
                </div>

                <div class="col-md-6">
                    <label class="font-weight-bold">Right Column Title</label>

                    <input type="text"
                           name="comparison_text[right_title]"
                           value="{{ old('comparison_text.right_title', $comparison['right_title'] ?? 'এটা চুইঝাল') }}"
                           class="form-control"
                           placeholder="Example: এটা চুইঝাল">
                </div>
            </div>

            <div class="row mb-4">
                @foreach(['image_one', 'image_two'] as $field)
                    @php
                        $mediaConfig = $mediaFields[$field];
                        $existingMedia = $campaign ? $campaign->getFirstMedia($field) : null;
                        $existingUrl = $existingMedia ? $existingMedia->getUrl() : null;
                    @endphp

                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">{{ $mediaConfig['label'] }}</label>

                        <input type="file"
                               name="{{ $field }}"
                               class="form-control-file campaign-media-input @error($field) is-invalid @enderror"
                               data-preview="#preview_{{ $field }}"
                               data-type="{{ $mediaConfig['type'] }}"
                               accept="image/*">

                        <small class="text-muted d-block mt-1">{{ $mediaConfig['hint'] }}</small>

                        @error($field)
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror

                        <div class="campaign-preview-box mt-2" id="preview_{{ $field }}">
                            @if($existingUrl)
                                <div class="existing-media-box position-relative d-inline-block">
                                    <img src="{{ $existingUrl }}" alt="{{ $mediaConfig['label'] }}" class="campaign-image-preview">

                                    <button type="button"
                                            class="btn btn-sm btn-danger campaign-media-delete-btn"
                                            data-url="{{ route('admin.campaigns.delete_media', $existingMedia->id) }}"
                                            data-target="#preview_{{ $field }}"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            @else
                                <div class="text-muted small border rounded p-3 bg-light">No file selected</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label class="font-weight-bold">Left Column Rows</label>

                    <div id="comparison-left-wrapper">
                        @foreach(($comparison['left'] ?? []) as $item)
                            <div class="input-group mb-2 comparison-left-item">
                                <input type="text"
                                       name="comparison_text[left][]"
                                       value="{{ $item }}"
                                       class="form-control"
                                       placeholder="Left comparison text">

                                <div class="input-group-append">
                                    <button type="button" class="btn btn-danger btn-remove-comparison">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddComparisonLeft">
                        <i class="fas fa-plus mr-1"></i> Add Left Row
                    </button>
                </div>

                <div class="col-md-6">
                    <label class="font-weight-bold">Right Column Rows</label>

                    <div id="comparison-right-wrapper">
                        @foreach(($comparison['right'] ?? []) as $item)
                            <div class="input-group mb-2 comparison-right-item">
                                <input type="text"
                                       name="comparison_text[right][]"
                                       value="{{ $item }}"
                                       class="form-control"
                                       placeholder="Right comparison text">

                                <div class="input-group-append">
                                    <button type="button" class="btn btn-danger btn-remove-comparison">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddComparisonRight">
                        <i class="fas fa-plus mr-1"></i> Add Right Row
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Service Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-truck text-success mr-1"></i>
                    Service Section
                </h5>

                <small class="text-muted">Frontend “কেন আমরাই সেরা” section-er icon/title/description ekhane theke dynamic hobe.</small>
            </div>

            {!! $sectionSwitch('service_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Service Section Title</label>

                <input type="text"
                       name="section_titles[service_title]"
                       value="{{ old('section_titles.service_title', $sectionTitles['service_title'] ?? 'কেন আমরাই সেরা') }}"
                       class="form-control"
                       placeholder="Service Section Title">
            </div>

            @php
                $field = 'image_three';
                $mediaConfig = $mediaFields[$field];
                $existingMedia = $campaign ? $campaign->getFirstMedia($field) : null;
                $existingUrl = $existingMedia ? $existingMedia->getUrl() : null;
            @endphp

            <div class="form-group">
                <label class="font-weight-bold">কেন আমরাই সেরা Section Image</label>

                <input type="file"
                       name="{{ $field }}"
                       class="form-control-file campaign-media-input @error($field) is-invalid @enderror"
                       data-preview="#preview_{{ $field }}"
                       data-type="{{ $mediaConfig['type'] }}"
                       accept="image/*">

                <small class="text-muted d-block mt-1">
                    এই image frontend home blade-এর “কেন আমরাই সেরা” section banner হিসেবে show হবে।
                </small>

                @error($field)
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror

                <div class="campaign-preview-box mt-2" id="preview_{{ $field }}">
                    @if($existingUrl)
                        <div class="existing-media-box position-relative d-inline-block">
                            <img src="{{ $existingUrl }}" alt="{{ $mediaConfig['label'] }}" class="campaign-image-preview">

                            <button type="button"
                                    class="btn btn-sm btn-danger campaign-media-delete-btn"
                                    data-url="{{ route('admin.campaigns.delete_media', $existingMedia->id) }}"
                                    data-target="#preview_{{ $field }}"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    @else
                        <div class="text-muted small border rounded p-3 bg-light">No file selected</div>
                    @endif
                </div>
            </div>

            <div class="row">
                @foreach($serviceItems as $index => $item)
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3">
                            <label class="font-weight-bold">Icon Class</label>

                            <input type="text"
                                   name="service_items[{{ $index }}][icon]"
                                   value="{{ $item['icon'] ?? '' }}"
                                   class="form-control mb-2"
                                   placeholder="Example: fas fa-truck">

                            <label class="font-weight-bold">Title</label>

                            <input type="text"
                                   name="service_items[{{ $index }}][title]"
                                   value="{{ $item['title'] ?? '' }}"
                                   class="form-control mb-2"
                                   placeholder="Service title">

                            <label class="font-weight-bold">Description</label>

                            <textarea name="service_items[{{ $index }}][description]"
                                      rows="2"
                                      class="form-control"
                                      placeholder="Service description">{{ $item['description'] ?? '' }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Gallery Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-images text-success mr-1"></i>
                    Product Gallery Section
                </h5>

                <small class="text-muted">Campaign form থেকে selected gallery images frontend home blade-এ show হবে।</small>
            </div>

            {!! $sectionSwitch('gallery_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Gallery Section Title</label>

                <input type="text"
                       name="section_titles[gallery_title]"
                       value="{{ old('section_titles.gallery_title', $sectionTitles['gallery_title'] ?? 'প্রোডাক্ট গ্যালারি') }}"
                       class="form-control"
                       placeholder="Gallery Section Title">
            </div>

            <div class="border rounded p-3 bg-light">
                <label class="font-weight-bold d-block">
                    <i class="fas fa-images text-success mr-1"></i>
                    প্রোডাক্ট গ্যালারি
                </label>

                <input type="file"
                       name="campaign_product_gallery[]"
                       id="campaignProductGalleryInput"
                       class="form-control-file @error('campaign_product_gallery') is-invalid @enderror @error('campaign_product_gallery.*') is-invalid @enderror"
                       accept="image/*"
                       multiple>

                <small class="text-muted d-block mt-1">
                    Campaign-wise product gallery images. একসাথে multiple photo select করা যাবে, preview হবে, selected photo instant remove করা যাবে।
                </small>

                @error('campaign_product_gallery')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror

                @error('campaign_product_gallery.*')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror

                <div class="row mt-3" id="campaignProductGallerySelectedPreview"></div>

                @if($isEdit && $campaign && method_exists($campaign, 'getMedia'))
                    <div class="row mt-3" id="campaignProductGalleryExistingPreview">
                        @foreach($campaign->getMedia('campaign_product_gallery') as $media)
                            <div class="col-md-3 col-lg-2 mb-3" id="campaign-gallery-media-box-{{ $media->id }}">
                                <div class="campaign-gallery-preview-item border rounded bg-white p-1">
                                    <img src="{{ $media->getUrl() }}"
                                         alt="Campaign Gallery Image"
                                         class="campaign-gallery-preview-img">

                                    <button type="button"
                                            class="btn btn-xs btn-danger btn-block mt-1 campaign-gallery-delete-btn"
                                            data-id="{{ $media->id }}"
                                            data-url="{{ route('admin.campaigns.delete_media', $media->id) }}">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Review Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-star text-warning mr-1"></i>
                    Review Section
                </h5>
                <small class="text-muted">Campaign form থেকে review add/update হবে।</small>
            </div>

            {!! $sectionSwitch('review_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Review Section Title</label>
                <input type="text"
                       name="section_titles[review_title]"
                       value="{{ old('section_titles.review_title', $sectionTitles['review_title'] ?? 'কাস্টমার রিভিউ') }}"
                       class="form-control"
                       placeholder="Review Section Title">
            </div>

            <div id="campaignReviewWrapper">
                @foreach($campaignReviewRows as $index => $review)
                    <div class="border rounded p-3 mb-3 campaign-review-item bg-light">
                        <input type="hidden" name="campaign_reviews[{{ $index }}][id]" value="{{ $review['id'] ?? '' }}">

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Review #{{ $loop->iteration }}</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-campaign-review">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">Customer Name</label>
                                <input type="text" name="campaign_reviews[{{ $index }}][customer_name]"
                                       value="{{ $review['customer_name'] ?? '' }}" class="form-control">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">Location</label>
                                <input type="text" name="campaign_reviews[{{ $index }}][location]"
                                       value="{{ $review['location'] ?? '' }}" class="form-control">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">Rating</label>
                                <select name="campaign_reviews[{{ $index }}][rating]" class="form-control">
                                    @for($rating = 1; $rating <= 5; $rating++)
                                        <option value="{{ $rating }}" @selected((int)($review['rating'] ?? 5) === $rating)>
                                            {{ $rating }} Star
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold">Social Link</label>
                                <input type="url" name="campaign_reviews[{{ $index }}][social_link]"
                                       value="{{ $review['social_link'] ?? '' }}" class="form-control">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold">Customer Image</label>
                                <input type="file" name="campaign_reviews[{{ $index }}][customer_image]"
                                       class="form-control-file" accept="image/*">

                                @if(! empty($review['image_url']))
                                    <div class="mt-2">
                                        <img src="{{ $review['image_url'] }}" class="campaign-image-preview" alt="Review Image">
                                        <div class="custom-control custom-checkbox mt-1">
                                            <input type="checkbox" class="custom-control-input"
                                                   id="remove_review_image_{{ $index }}"
                                                   name="campaign_reviews[{{ $index }}][remove_image]" value="1">
                                            <label class="custom-control-label" for="remove_review_image_{{ $index }}">Remove current image</label>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="font-weight-bold">Review Text</label>
                                <textarea name="campaign_reviews[{{ $index }}][review_text]" rows="3" class="form-control">{{ $review['review_text'] ?? '' }}</textarea>
                            </div>

                            <div class="col-md-12">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input"
                                           id="campaign_review_status_{{ $index }}"
                                           name="campaign_reviews[{{ $index }}][status]" value="1"
                                           @checked($review['status'] ?? true)>
                                    <label class="custom-control-label" for="campaign_review_status_{{ $index }}">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddCampaignReview">
                <i class="fas fa-plus mr-1"></i> Add Review
            </button>
        </div>
    </div>

    {{-- FAQ Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-question-circle text-info mr-1"></i>
                    FAQ Section
                </h5>
                <small class="text-muted">Campaign form থেকে FAQ add/update হবে।</small>
            </div>

            {!! $sectionSwitch('faq_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">FAQ Section Title</label>
                <input type="text"
                       name="section_titles[faq_title]"
                       value="{{ old('section_titles.faq_title', $sectionTitles['faq_title'] ?? 'সচরাচর জিজ্ঞাস্য প্রশ্নাবলি') }}"
                       class="form-control"
                       placeholder="FAQ Section Title">
            </div>

            <div id="campaignFaqWrapper">
                @foreach($campaignFaqRows as $index => $faq)
                    <div class="border rounded p-3 mb-3 campaign-faq-item bg-light">
                        <input type="hidden" name="campaign_faqs[{{ $index }}][id]" value="{{ $faq['id'] ?? '' }}">
                        <input type="hidden" name="campaign_faqs[{{ $index }}][sort_order]" value="{{ $faq['sort_order'] ?? $index }}">

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>FAQ #{{ $loop->iteration }}</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-campaign-faq">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Question</label>
                            <input type="text" name="campaign_faqs[{{ $index }}][question]"
                                   value="{{ $faq['question'] ?? '' }}" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Answer</label>
                            <textarea name="campaign_faqs[{{ $index }}][answer]" rows="3" class="form-control">{{ $faq['answer'] ?? '' }}</textarea>
                        </div>

                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input"
                                   id="campaign_faq_status_{{ $index }}"
                                   name="campaign_faqs[{{ $index }}][status]" value="1"
                                   @checked($faq['status'] ?? true)>
                            <label class="custom-control-label" for="campaign_faq_status_{{ $index }}">Active</label>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddCampaignFaq">
                <i class="fas fa-plus mr-1"></i> Add FAQ
            </button>
        </div>
    </div>

    {{-- Order Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-shopping-cart text-success mr-1"></i>
                    Order Section
                </h5>

                <small class="text-muted">Frontend order section title and order form text.</small>
            </div>

            {!! $sectionSwitch('order_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Order Section Title</label>

                <input type="text"
                       name="section_titles[order_title]"
                       value="{{ old('section_titles.order_title', $sectionTitles['order_title'] ?? 'অর্ডার করুন এখনই') }}"
                       class="form-control"
                       placeholder="Order Section Title">
            </div>

            <div class="alert alert-light border mb-0">
                <i class="fas fa-info-circle text-primary mr-1"></i>
                Order Form Title এবং Order Form Subtitle উপরের Basic Information section থেকে manage হবে।
            </div>
        </div>
    </div>

    {{-- Help Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-headset text-success mr-1"></i>
                    Help Section
                </h5>

                <small class="text-muted">
                    Frontend help CTA section dynamic হবে।
                </small>
            </div>

            {!! $sectionSwitch('help_section_status', 'Active / Inactive') !!}
        </div>

        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Help Title</label>

                <input type="text"
                       name="help_content[title]"
                       value="{{ old('help_content.title', $helpContent['title'] ?? 'সাহায্য প্রয়োজন?') }}"
                       class="form-control">
            </div>

            <div class="form-group">
                <label class="font-weight-bold">Help Description</label>

                <textarea name="help_content[description]"
                          rows="3"
                          class="form-control">{{ old('help_content.description', $helpContent['description'] ?? '') }}</textarea>
            </div>

            <div class="form-group mb-0">
                <label class="font-weight-bold">Help Button Text</label>

                <input type="text"
                       name="help_content[button_text]"
                       value="{{ old('help_content.button_text', $helpContent['button_text'] ?? 'হেল্পলাইন') }}"
                       class="form-control">
            </div>
        </div>
    </div>

    {{-- SEO --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-search text-primary mr-1"></i>
                SEO Information
            </h5>
        </div>

        <div class="card-body">
            <div class="form-group mb-0">
                <label class="font-weight-bold">Meta Title</label>

                <input type="text"
                       name="meta_title"
                       value="{{ old('meta_title', $campaign->meta_title ?? '') }}"
                       class="form-control @error('meta_title') is-invalid @enderror"
                       placeholder="Meta title">

                @error('meta_title')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-secondary mb-2 mb-md-0">
                <i class="fas fa-arrow-left mr-1"></i>
                Back
            </a>

            <div class="d-flex align-items-center flex-wrap justify-content-end">
                <div class="custom-control custom-switch mr-4 mb-2 mb-md-0">
                    <input type="checkbox"
                           name="enable_bulk_order"
                           value="1"
                           class="custom-control-input"
                           id="enable_bulk_order"
                           @checked(old('enable_bulk_order', $campaign->enable_bulk_order ?? false))>

                    <label class="custom-control-label font-weight-bold" for="enable_bulk_order">
                        Enable Bulk Order
                    </label>
                </div>

                <div class="custom-control custom-switch mr-4 mb-2 mb-md-0">
                    <input type="checkbox"
                           name="status"
                           value="1"
                           class="custom-control-input"
                           id="campaign_status"
                           @checked(old('status', $campaign->status ?? true))>

                    <label class="custom-control-label font-weight-bold" for="campaign_status">
                        Active Campaign
                    </label>
                </div>

                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save mr-1"></i>
                    {{ $isEdit ? 'Update Campaign' : 'Save Campaign' }}
                </button>
            </div>
        </div>
    </div>
</form>

@once
@push('css')
<style>
.section-status-switch {
    margin-top: 4px;
}

.campaign-image-preview {
    width: 150px;
    height: 110px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
}

.campaign-video-preview {
    width: 190px;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #111827;
}

.campaign-media-delete-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    padding: 0;
}

.campaign-gallery-preview-item {
    position: relative;
}

.campaign-gallery-preview-img {
    width: 100%;
    height: 110px;
    object-fit: cover;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
}

.campaign-gallery-remove-selected {
    position: absolute;
    top: 5px;
    right: 5px;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    padding: 0;
    line-height: 26px;
}

.benefit-item .input-group,
.comparison-left-item,
.comparison-right-item {
    box-shadow: 0 1px 2px rgba(0, 0, 0, .03);
}

.select2-container--default .select2-selection--multiple {
    min-height: 42px;
    border-color: #ced4da;
    border-radius: 6px;
}

.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #6c63ff;
    box-shadow: 0 0 0 0.15rem rgba(108, 99, 255, 0.15);
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #6c63ff;
    border-color: #6c63ff;
    color: #ffffff;
    border-radius: 4px;
    padding: 2px 8px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: #ffffff;
    margin-right: 5px;
}

.section-header-field-inactive {
    opacity: 0.55;
    position: relative;
}

.section-header-field-inactive > label::after,
.section-header-field-inactive label.font-weight-bold::after {
    content: " Inactive";
    display: inline-block;
    margin-left: 8px;
    padding: 2px 7px;
    font-size: 10px;
    font-weight: 700;
    color: #dc3545;
    background: #fff5f5;
    border: 1px solid #f5c2c7;
    border-radius: 20px;
}

.section-header-field-inactive input:disabled,
.section-header-field-inactive textarea:disabled,
.section-header-field-inactive select:disabled {
    background-color: #f8f9fa !important;
    cursor: not-allowed;
}

.section-header-field-inactive .select2-container--disabled .select2-selection,
.section-header-field-inactive .select2-container--default.select2-container--disabled .select2-selection--single,
.section-header-field-inactive .select2-container--default.select2-container--disabled .select2-selection--multiple {
    background-color: #f8f9fa !important;
    cursor: not-allowed;
}

.section-header-hidden-mirror {
    display: none !important;
}
</style>
@endpush

@push('js')
<script>
$(document).ready(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    });

    if ($.fn.select2) {
        $('.select2-categories').select2({
            placeholder: 'Select categories',
            width: '100%',
            closeOnSelect: false
        });

        $('.select2-brands').select2({
            placeholder: 'Select brands',
            width: '100%',
            closeOnSelect: false
        });

        $('.select2-products').select2({
            placeholder: 'Select products',
            width: '100%',
            closeOnSelect: false
        });
    }

    function showToast(type, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type,
                type: type,
                title: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2200,
                timerProgressBar: true
            });

            return;
        }

        alert(message);
    }

    $('#btnAddBenefit').on('click', function() {
        $('#benefits-wrapper').append(`
            <div class="col-md-4 benefit-item mb-2">
                <div class="input-group">
                    <input type="text"
                           name="benefits_text[]"
                           class="form-control"
                           placeholder="Benefit text">

                    <div class="input-group-append">
                        <button type="button" class="btn btn-danger btn-remove-benefit">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `);
    });

    $(document).on('click', '.btn-remove-benefit', function() {
        $(this).closest('.benefit-item').remove();
    });

    $('#btnAddComparisonLeft').on('click', function() {
        $('#comparison-left-wrapper').append(`
            <div class="input-group mb-2 comparison-left-item">
                <input type="text"
                       name="comparison_text[left][]"
                       class="form-control"
                       placeholder="Left comparison text">

                <div class="input-group-append">
                    <button type="button" class="btn btn-danger btn-remove-comparison">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `);
    });

    $('#btnAddComparisonRight').on('click', function() {
        $('#comparison-right-wrapper').append(`
            <div class="input-group mb-2 comparison-right-item">
                <input type="text"
                       name="comparison_text[right][]"
                       class="form-control"
                       placeholder="Right comparison text">

                <div class="input-group-append">
                    <button type="button" class="btn btn-danger btn-remove-comparison">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `);
    });

    $(document).on('click', '.btn-remove-comparison', function() {
        $(this).closest('.input-group').remove();
    });

    $(document).on('change', '.campaign-media-input', function() {
        const input = this;
        const file = input.files && input.files[0] ? input.files[0] : null;
        const previewSelector = $(input).data('preview');
        const type = $(input).data('type');
        const previewBox = $(previewSelector);

        if (!file) {
            previewBox.html(`
                <div class="text-muted small border rounded p-3 bg-light">
                    No file selected
                </div>
            `);

            return;
        }

        const fileUrl = URL.createObjectURL(file);

        if (type === 'video') {
            previewBox.html(`
                <div class="existing-media-box position-relative d-inline-block">
                    <video src="${fileUrl}"
                           controls
                           class="campaign-video-preview"></video>

                    <button type="button"
                            class="btn btn-sm btn-danger btn-remove-selected-media"
                            title="Remove selected file">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
        } else {
            previewBox.html(`
                <div class="existing-media-box position-relative d-inline-block">
                    <img src="${fileUrl}"
                         alt="Preview"
                         class="campaign-image-preview">

                    <button type="button"
                            class="btn btn-sm btn-danger btn-remove-selected-media"
                            title="Remove selected file">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
        }

        previewBox.find('.btn-remove-selected-media').css({
            position: 'absolute',
            top: '4px',
            right: '4px',
            borderRadius: '50%',
            width: '30px',
            height: '30px',
            padding: '0'
        });
    });

    $(document).on('click', '.btn-remove-selected-media', function() {
        const previewBox = $(this).closest('.campaign-preview-box');
        const previewId = '#' + previewBox.attr('id');

        $('.campaign-media-input[data-preview="' + previewId + '"]').val('');

        previewBox.html(`
            <div class="text-muted small border rounded p-3 bg-light">
                No file selected
            </div>
        `);
    });

    $(document).on('click', '.campaign-media-delete-btn', function() {
        const button = $(this);
        const url = button.data('url');
        const target = button.data('target');

        if (!url) {
            showToast('error', 'Delete URL not found.');
            return;
        }

        Swal.fire({
            title: 'Delete media?',
            text: 'This media file will be deleted permanently.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            confirmButtonColor: '#dc3545'
        }).then(function(result) {
            if (!(result.isConfirmed || result.value)) {
                return;
            }

            $.ajax({
                url: url,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                beforeSend: function() {
                    button.prop('disabled', true);
                },
                success: function(res) {
                    if (res.status) {
                        $(target).html(`
                            <div class="text-muted small border rounded p-3 bg-light">
                                No file selected
                            </div>
                        `);

                        showToast('success', res.message || 'Media deleted successfully.');
                    } else {
                        button.prop('disabled', false);
                        showToast('error', res.message || 'Media delete failed.');
                    }
                },
                error: function(xhr) {
                    button.prop('disabled', false);

                    let message = 'Media delete failed.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    showToast('error', message);
                }
            });
        });
    });


    let heroSliderFiles = [];

    function refreshHeroSliderInput() {
        const input = document.getElementById('heroSliderImagesInput');

        if (!input) {
            return;
        }

        const dataTransfer = new DataTransfer();

        heroSliderFiles.forEach(function(file) {
            dataTransfer.items.add(file);
        });

        input.files = dataTransfer.files;
    }

    function renderHeroSliderSelectedPreview() {
        const wrapper = $('#heroSliderImagesSelectedPreview');

        wrapper.html('');

        if (!heroSliderFiles.length) {
            return;
        }

        heroSliderFiles.forEach(function(file, index) {
            const fileUrl = URL.createObjectURL(file);

            wrapper.append(`
                <div class="col-md-3 col-lg-2 mb-3">
                    <div class="campaign-gallery-preview-item border rounded bg-white p-1">
                        <img src="${fileUrl}"
                             alt="Selected Hero Slider Image"
                             class="campaign-gallery-preview-img">

                        <button type="button"
                                class="btn btn-sm btn-danger campaign-gallery-remove-selected hero-slider-remove-selected"
                                data-index="${index}"
                                title="Remove selected image">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `);
        });
    }

    $(document).on('change', '#heroSliderImagesInput', function() {
        const files = Array.from(this.files || []);

        files.forEach(function(file) {
            if (file && file.type && file.type.startsWith('image/')) {
                heroSliderFiles.push(file);
            }
        });

        refreshHeroSliderInput();
        renderHeroSliderSelectedPreview();
    });

    $(document).on('click', '.hero-slider-remove-selected', function() {
        const index = Number($(this).data('index'));

        heroSliderFiles.splice(index, 1);

        refreshHeroSliderInput();
        renderHeroSliderSelectedPreview();
    });

    $(document).on('click', '.hero-slider-delete-btn', function() {
        const button = $(this);
        const url = button.data('url');
        const mediaId = button.data('id');

        if (!url) {
            showToast('error', 'Delete URL not found.');
            return;
        }

        Swal.fire({
            title: 'Delete hero slider image?',
            text: 'This image will be deleted permanently.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545'
        }).then(function(result) {
            if (!(result.isConfirmed || result.value)) {
                return;
            }

            $.ajax({
                url: url,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                beforeSend: function() {
                    button.prop('disabled', true)
                        .html('<i class="fas fa-spinner fa-spin"></i>');
                },
                success: function(res) {
                    if (res.status) {
                        $('#hero-slider-media-box-' + mediaId).fadeOut(250, function() {
                            $(this).remove();
                        });

                        showToast('success', res.message || 'Hero slider image deleted successfully.');
                    } else {
                        button.prop('disabled', false).html('Delete');
                        showToast('error', res.message || 'Hero slider image delete failed.');
                    }
                },
                error: function(xhr) {
                    button.prop('disabled', false).html('Delete');

                    let message = 'Hero slider image delete failed.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    showToast('error', message);
                }
            });
        });
    });


    let campaignGalleryFiles = [];

    function refreshCampaignGalleryInput() {
        const input = document.getElementById('campaignProductGalleryInput');

        if (!input) {
            return;
        }

        const dataTransfer = new DataTransfer();

        campaignGalleryFiles.forEach(function(file) {
            dataTransfer.items.add(file);
        });

        input.files = dataTransfer.files;
    }

    function renderCampaignGallerySelectedPreview() {
        const wrapper = $('#campaignProductGallerySelectedPreview');

        wrapper.html('');

        if (!campaignGalleryFiles.length) {
            return;
        }

        campaignGalleryFiles.forEach(function(file, index) {
            const fileUrl = URL.createObjectURL(file);

            wrapper.append(`
                <div class="col-md-3 col-lg-2 mb-3">
                    <div class="campaign-gallery-preview-item border rounded bg-white p-1">
                        <img src="${fileUrl}"
                             alt="Selected Gallery Image"
                             class="campaign-gallery-preview-img">

                        <button type="button"
                                class="btn btn-sm btn-danger campaign-gallery-remove-selected"
                                data-index="${index}"
                                title="Remove selected image">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `);
        });
    }

    $(document).on('change', '#campaignProductGalleryInput', function() {
        const files = Array.from(this.files || []);

        files.forEach(function(file) {
            if (file && file.type && file.type.startsWith('image/')) {
                campaignGalleryFiles.push(file);
            }
        });

        refreshCampaignGalleryInput();
        renderCampaignGallerySelectedPreview();
    });

    $(document).on('click', '.campaign-gallery-remove-selected', function() {
        const index = Number($(this).data('index'));

        campaignGalleryFiles.splice(index, 1);

        refreshCampaignGalleryInput();
        renderCampaignGallerySelectedPreview();
    });

    $(document).on('click', '.campaign-gallery-delete-btn', function() {
        const button = $(this);
        const url = button.data('url');
        const mediaId = button.data('id');

        if (!url) {
            showToast('error', 'Delete URL not found.');
            return;
        }

        Swal.fire({
            title: 'Delete gallery image?',
            text: 'This image will be deleted permanently.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545'
        }).then(function(result) {
            if (!(result.isConfirmed || result.value)) {
                return;
            }

            $.ajax({
                url: url,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                beforeSend: function() {
                    button.prop('disabled', true)
                        .html('<i class="fas fa-spinner fa-spin"></i>');
                },
                success: function(res) {
                    if (res.status) {
                        $('#campaign-gallery-media-box-' + mediaId).fadeOut(250, function() {
                            $(this).remove();
                        });

                        showToast('success', res.message || 'Gallery image deleted successfully.');
                    } else {
                        button.prop('disabled', false).html('Delete');
                        showToast('error', res.message || 'Gallery image delete failed.');
                    }
                },
                error: function(xhr) {
                    button.prop('disabled', false).html('Delete');

                    let message = 'Gallery image delete failed.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    showToast('error', message);
                }
            });
        });
    });

    let campaignFaqIndex = {{ $campaignFaqRows->count() }};
    let campaignReviewIndex = {{ $campaignReviewRows->count() }};

    $('#btnAddCampaignFaq').on('click', function () {
        const index = campaignFaqIndex++;

        $('#campaignFaqWrapper').append(`
            <div class="border rounded p-3 mb-3 campaign-faq-item bg-light">
                <input type="hidden" name="campaign_faqs[${index}][id]" value="">
                <input type="hidden" name="campaign_faqs[${index}][sort_order]" value="${index}">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>FAQ</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-campaign-faq">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Question</label>
                    <input type="text" name="campaign_faqs[${index}][question]" class="form-control">
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Answer</label>
                    <textarea name="campaign_faqs[${index}][answer]" rows="3" class="form-control"></textarea>
                </div>

                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input"
                           id="campaign_faq_status_${index}"
                           name="campaign_faqs[${index}][status]" value="1" checked>
                    <label class="custom-control-label" for="campaign_faq_status_${index}">Active</label>
                </div>
            </div>
        `);
    });

    $(document).on('click', '.btn-remove-campaign-faq', function () {
        $(this).closest('.campaign-faq-item').remove();
    });

    $('#btnAddCampaignReview').on('click', function () {
        const index = campaignReviewIndex++;

        $('#campaignReviewWrapper').append(`
            <div class="border rounded p-3 mb-3 campaign-review-item bg-light">
                <input type="hidden" name="campaign_reviews[${index}][id]" value="">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Review</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-campaign-review">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Customer Name</label>
                        <input type="text" name="campaign_reviews[${index}][customer_name]" class="form-control">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Location</label>
                        <input type="text" name="campaign_reviews[${index}][location]" class="form-control">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">Rating</label>
                        <select name="campaign_reviews[${index}][rating]" class="form-control">
                            <option value="1">1 Star</option>
                            <option value="2">2 Star</option>
                            <option value="3">3 Star</option>
                            <option value="4">4 Star</option>
                            <option value="5" selected>5 Star</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Social Link</label>
                        <input type="url" name="campaign_reviews[${index}][social_link]" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">Customer Image</label>
                        <input type="file" name="campaign_reviews[${index}][customer_image]" class="form-control-file" accept="image/*">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="font-weight-bold">Review Text</label>
                        <textarea name="campaign_reviews[${index}][review_text]" rows="3" class="form-control"></textarea>
                    </div>

                    <div class="col-md-12">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input"
                                   id="campaign_review_status_${index}"
                                   name="campaign_reviews[${index}][status]" value="1" checked>
                            <label class="custom-control-label" for="campaign_review_status_${index}">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        `);
    });

    $(document).on('click', '.btn-remove-campaign-review', function () {
        $(this).closest('.campaign-review-item').remove();
    });

});
</script>
<script>
(function ($) {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | Campaign Section Header Active / Inactive Toggle
    |--------------------------------------------------------------------------
    | Section switch OFF করলে related header/title fields সত্যিকারের disabled
    | থাকবে। Disabled field submit হয় না, তাই hidden mirror field ব্যবহার করা
    | হয়েছে যেন existing title value হারিয়ে না যায়।
    */

    const sectionHeaderFieldMap = {
        hero_section_status: [
            'section_titles[hero_star_count]',
            'section_titles[hero_rating_text]'
        ],

        category_section_status: [
            'section_titles[category_title]',
            'section_titles[brand_title]'
        ],

        product_section_status: [
            'section_titles[product_title]',
            'section_titles[category_filter_title]',
            'section_titles[brand_filter_title]'
        ],

        comparison_section_status: [
            'section_titles[comparison_title]',
            'comparison_text[left_title]',
            'comparison_text[right_title]'
        ],

        service_section_status: [
            'section_titles[service_title]'
        ],

        review_section_status: [
            'section_titles[review_title]'
        ],

        gallery_section_status: [
            'section_titles[gallery_title]'
        ],

        faq_section_status: [
            'section_titles[faq_title]'
        ],

        help_section_status: [
            'help_content[title]',
            'help_content[description]',
            'help_content[button_text]'
        ],

        order_section_status: [
            'section_titles[order_title]'
        ]
    };

    function injectSectionHeaderToggleStyle() {
        if ($('#campaign-section-toggle-style').length) {
            return;
        }

        $('head').append(`
            <style id="campaign-section-toggle-style">
                .section-header-field-inactive {
                    opacity: 0.55;
                    position: relative;
                }

                .section-header-field-inactive > label::after,
                .section-header-field-inactive label.font-weight-bold::after {
                    content: " Inactive";
                    display: inline-block;
                    margin-left: 8px;
                    padding: 2px 7px;
                    font-size: 10px;
                    font-weight: 700;
                    color: #dc3545;
                    background: #fff5f5;
                    border: 1px solid #f5c2c7;
                    border-radius: 20px;
                }

                .section-header-field-inactive input:disabled,
                .section-header-field-inactive textarea:disabled,
                .section-header-field-inactive select:disabled {
                    background-color: #f8f9fa !important;
                    cursor: not-allowed;
                }

                .section-header-field-inactive .select2-container--disabled .select2-selection,
                .section-header-field-inactive .select2-container--default.select2-container--disabled .select2-selection--single,
                .section-header-field-inactive .select2-container--default.select2-container--disabled .select2-selection--multiple {
                    background-color: #f8f9fa !important;
                    cursor: not-allowed;
                }

                .section-header-hidden-mirror {
                    display: none !important;
                }
            </style>
        `);
    }

    function getFormFieldByExactName(fieldName) {
        return $('[name]').filter(function () {
            return this.name === fieldName && !$(this).is('[data-section-header-hidden-mirror]');
        });
    }

    function getFieldWrapper(field) {
        return field.closest(
            '.form-group, .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-md-12'
        );
    }

    function ensureHiddenMirror(field) {
        const fieldName = field.attr('name');
        const wrapper = getFieldWrapper(field);

        let mirror = wrapper.find('input[type="hidden"][data-section-header-hidden-mirror="1"]').filter(function () {
            return this.name === fieldName;
        }).first();

        if (!mirror.length) {
            mirror = $('<input>', {
                type: 'hidden',
                name: fieldName,
                class: 'section-header-hidden-mirror',
                'data-section-header-hidden-mirror': '1',
                disabled: true
            });

            field.after(mirror);
        }

        mirror.val(field.val());

        field.off('input.sectionHeaderMirror change.sectionHeaderMirror')
            .on('input.sectionHeaderMirror change.sectionHeaderMirror', function () {
                mirror.val($(this).val());
            });

        return mirror;
    }

    function toggleRelatedHeaderFields(sectionSwitchId) {
        const switchInput = $('#' + sectionSwitchId);

        if (!switchInput.length) {
            return;
        }

        const isActive = switchInput.is(':checked');
        const fieldNames = sectionHeaderFieldMap[sectionSwitchId] || [];

        fieldNames.forEach(function (fieldName) {
            const field = getFormFieldByExactName(fieldName);

            if (!field.length) {
                return;
            }

            field.each(function () {
                const currentField = $(this);
                const wrapper = getFieldWrapper(currentField);
                const mirror = ensureHiddenMirror(currentField);

                wrapper.toggleClass('section-header-field-inactive', !isActive);

                if (isActive) {
                    currentField.prop('disabled', false);
                    mirror.prop('disabled', true);
                } else {
                    mirror.val(currentField.val()).prop('disabled', false);
                    currentField.prop('disabled', true);
                }

                if (currentField.hasClass('select2-hidden-accessible')) {
                    currentField.trigger('change.select2');
                }
            });
        });
    }

    function syncDisabledHeaderFieldsBeforeSubmit() {
        Object.keys(sectionHeaderFieldMap).forEach(function (sectionSwitchId) {
            const isActive = $('#' + sectionSwitchId).is(':checked');

            if (isActive) {
                return;
            }

            (sectionHeaderFieldMap[sectionSwitchId] || []).forEach(function (fieldName) {
                const field = getFormFieldByExactName(fieldName);

                field.each(function () {
                    const currentField = $(this);
                    const mirror = ensureHiddenMirror(currentField);

                    mirror.val(currentField.val()).prop('disabled', false);
                    currentField.prop('disabled', true);
                });
            });
        });
    }

    function initCampaignSectionToggle() {
        injectSectionHeaderToggleStyle();

        Object.keys(sectionHeaderFieldMap).forEach(function (sectionSwitchId) {
            toggleRelatedHeaderFields(sectionSwitchId);

            $(document).on('change', '#' + sectionSwitchId, function () {
                toggleRelatedHeaderFields(sectionSwitchId);
            });
        });

        $(document).on('submit', '#campaignForm', function () {
            syncDisabledHeaderFieldsBeforeSubmit();
        });
    }

    $(document).ready(function () {
        initCampaignSectionToggle();
    });

})(jQuery);
</script>
@endpush
@endonce