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

    $sectionSwitches = [
        'hero_section_status' => 'Hero Section',
        'benefits_section_status' => 'Hero Benefits Section',
        'category_section_status' => 'Category / Brand Filter Section',
        'product_section_status' => 'Product Section',
        'comparison_section_status' => 'Comparison Section',
        'service_section_status' => 'Why Choose Us / Service Section',
        'review_section_status' => 'Review Section',
        'gallery_section_status' => 'Gallery Section',
        'faq_section_status' => 'FAQ Section',
        'order_section_status' => 'Order Section',
    ];

    $mediaFields = [
        'banner_image' => [
            'label' => 'Banner Image',
            'type' => 'image',
            'hint' => 'Recommended size: 800x600px',
        ],
        'image_one' => [
            'label' => 'Image One',
            'type' => 'image',
            'hint' => 'Used in campaign sections',
        ],
        'image_two' => [
            'label' => 'Image Two',
            'type' => 'image',
            'hint' => 'Used in comparison left image',
        ],
        'image_three' => [
            'label' => 'Image Three',
            'type' => 'image',
            'hint' => 'Used in comparison right image',
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
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data" id="campaignForm">
    @csrf

    @if($isEdit)
        @method('PUT')
    @endif

    {{-- Basic Campaign Information --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-bullhorn text-primary mr-1"></i>
                Basic Campaign Information
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

                <div class="col-md-12 mb-3">
                    <label class="font-weight-bold">Full Description</label>

                    <textarea name="full_description"
                              rows="5"
                              class="form-control @error('full_description') is-invalid @enderror"
                              placeholder="Full campaign description">{{ old('full_description', $campaign->full_description ?? '') }}</textarea>

                    @error('full_description')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold">Offer Text</label>

                    <input type="text"
                           name="offer_text"
                           value="{{ old('offer_text', $campaign->offer_text ?? '') }}"
                           class="form-control @error('offer_text') is-invalid @enderror"
                           placeholder="Example: Limited time offer">

                    @error('offer_text')
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

                <div class="col-md-6 mb-2">
                    <div class="custom-control custom-switch">
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
                </div>

                <div class="col-md-6 mb-2">
                    <div class="custom-control custom-switch">
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
                </div>
            </div>
        </div>
    </div>

    {{-- Frontend Section Active / Inactive --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-toggle-on text-success mr-1"></i>
                Frontend Section Active / Inactive
            </h5>

            <small class="text-muted">
                Switch active থাকলে frontend home page-এ section show হবে, inactive থাকলে hide হবে।
            </small>
        </div>

        <div class="card-body">
            <div class="row">
                @foreach($sectionSwitches as $field => $label)
                    <div class="col-md-4 mb-2">
                        <div class="custom-control custom-switch">
                            <input type="checkbox"
                                   name="{{ $field }}"
                                   value="1"
                                   class="custom-control-input"
                                   id="{{ $field }}"
                                   @checked(old($field, $campaign->{$field} ?? true))>

                            <label class="custom-control-label" for="{{ $field }}">
                                {{ $label }}
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Frontend Section Titles --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-heading text-success mr-1"></i>
                Frontend Section Titles
            </h5>

            <small class="text-muted">
                Frontend home page-এর section title এখান থেকে dynamic হবে।
            </small>
        </div>

        <div class="card-body">
            <div class="row">
                @foreach([
                    'category_title' => 'Category Section Title',
                    'brand_title' => 'Brand Section Title',
                    'product_title' => 'Product Section Title',
                    'category_filter_title' => 'Category Filter Title',
                    'brand_filter_title' => 'Brand Filter Title',
                    'comparison_title' => 'Comparison Section Title',
                    'service_title' => 'Service Section Title',
                    'review_title' => 'Review Section Title',
                    'faq_title' => 'FAQ Section Title',
                    'gallery_title' => 'Gallery Section Title',
                    'order_title' => 'Order Section Title',
                ] as $field => $label)
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">{{ $label }}</label>

                        <input type="text"
                               name="section_titles[{{ $field }}]"
                               value="{{ old('section_titles.' . $field, $sectionTitles[$field] ?? '') }}"
                               class="form-control"
                               placeholder="{{ $label }}">
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Hero Benefits Text --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-check-circle text-success mr-1"></i>
                Hero Benefits Text
            </h5>

            <small class="text-muted">
                Frontend hero section-er benefit list ekhane theke dynamic hobe.
            </small>
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

    {{-- Comparison Section --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-balance-scale text-success mr-1"></i>
                Comparison Section
            </h5>

            <small class="text-muted">
                Frontend-er comparison section ekhane theke dynamic hobe.
            </small>
        </div>

        <div class="card-body">
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

    {{-- Campaign Category / Brand / Product Selection --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-layer-group text-primary mr-1"></i>
                Campaign Category / Brand / Product Selection
            </h5>

            <small class="text-muted">
                Admin যে order-এ select করবে, frontend-এ সেই order-এ category, brand এবং product show হবে।
            </small>
        </div>

        <div class="card-body">
            <div class="form-group">
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

            <div class="form-group">
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

    {{-- Service Section Items --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-truck text-success mr-1"></i>
                Service Section Items
            </h5>

            <small class="text-muted">
                Frontend “কেন আমরাই সেরা” section-er icon/title/description ekhane theke dynamic hobe.
            </small>
        </div>

        <div class="card-body">
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

    {{-- Help Section Content --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-headset text-success mr-1"></i>
                Help Section Content
            </h5>

            <small class="text-muted">
                Frontend help CTA section dynamic হবে।
            </small>
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

    {{-- Media Upload --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
        <div class="card-header bg-white">
            <h5 class="mb-0 font-weight-bold">
                <i class="fas fa-images text-primary mr-1"></i>
                Campaign Media
            </h5>

            <small class="text-muted">
                Image/video select korar por preview dekhabe. Embed video URL ekhanei add kora jabe. Existing media delete option thakbe.
            </small>
        </div>

        <div class="card-body">
            <div class="form-group mb-4">
                <label class="font-weight-bold">Embed Video URL</label>

                <input type="url"
                       name="embed_video_url"
                       value="{{ old('embed_video_url', $campaign->embed_video_url ?? '') }}"
                       class="form-control @error('embed_video_url') is-invalid @enderror"
                       placeholder="YouTube/Facebook video URL. Example: https://youtu.be/VIDEO_ID">

                <small class="text-muted">
                    Campaign Video upload অথবা Embed Video URL — যেকোনো একটা use করতে পারবে। Embed URL দিলে frontend-এ embed video আগে show হবে।
                </small>

                @error('embed_video_url')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            </div>

            <div class="row">
                @foreach($mediaFields as $field => $mediaConfig)
                    @php
                        $existingMedia = $campaign ? $campaign->getFirstMedia($field) : null;
                        $existingUrl = $existingMedia ? $existingMedia->getUrl() : null;
                    @endphp

                    <div class="col-md-6 col-lg-4 mb-4">
                        <label class="font-weight-bold">{{ $mediaConfig['label'] }}</label>

                        <input type="file"
                               name="{{ $field }}"
                               class="form-control-file campaign-media-input @error($field) is-invalid @enderror"
                               data-preview="#preview_{{ $field }}"
                               data-type="{{ $mediaConfig['type'] }}"
                               accept="{{ $mediaConfig['type'] === 'video' ? 'video/mp4,video/webm,video/ogg' : 'image/*' }}">

                        <small class="text-muted d-block mt-1">
                            {{ $mediaConfig['hint'] }}
                        </small>

                        @error($field)
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror

                        <div class="campaign-preview-box mt-2" id="preview_{{ $field }}">
                            @if($existingUrl)
                                <div class="existing-media-box position-relative d-inline-block">
                                    @if($mediaConfig['type'] === 'video')
                                        <video src="{{ $existingUrl }}" controls class="campaign-video-preview"></video>
                                    @else
                                        <img src="{{ $existingUrl }}"
                                             alt="{{ $mediaConfig['label'] }}"
                                             class="campaign-image-preview">
                                    @endif

                                    <button type="button"
                                            class="btn btn-sm btn-danger campaign-media-delete-btn"
                                            data-url="{{ route('admin.campaigns.delete_media', $existingMedia->id) }}"
                                            data-target="#preview_{{ $field }}"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            @else
                                <div class="text-muted small border rounded p-3 bg-light">
                                    No file selected
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- Campaign Product Gallery --}}
                <div class="col-12 mt-2">
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
            <div class="form-group">
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

            <div class="form-group mb-0">
                <label class="font-weight-bold">Meta Description</label>

                <textarea name="meta_description"
                          rows="3"
                          class="form-control @error('meta_description') is-invalid @enderror"
                          placeholder="Meta description">{{ old('meta_description', $campaign->meta_description ?? '') }}</textarea>

                @error('meta_description')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>
                Back
            </a>

            <button type="submit" class="btn btn-primary px-4">
                <i class="fas fa-save mr-1"></i>
                {{ $isEdit ? 'Update Campaign' : 'Save Campaign' }}
            </button>
        </div>
    </div>
</form>

@once
@push('css')
<style>
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
});
</script>
@endpush
@endonce