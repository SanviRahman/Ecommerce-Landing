<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\MediaLibrary\LandingMediaSectionMap;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Faq;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\ShippingCharge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CampaignController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function campaignQuery(bool $trash = false): Builder
    {
        $query = $trash ? Campaign::onlyTrashed() : Campaign::query();

        return $query
            ->with(['products', 'categories', 'brands', 'faqs', 'reviews'])
            ->orderBy('id', 'asc');
    }

    /**
     * Return active products for the campaign form.
     *
     * Important: Select2 submits selected options according to the DOM option order.
     * So on edit, selected products must be rendered first using the campaign_product.sort_order
     * value; otherwise products can be saved/displayed alphabetically instead of the admin-selected order.
     */
    private function activeProducts(array $selectedProductIds = [])
    {
        $selectedProductIds = collect($selectedProductIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $products = Product::query()
            ->active()
            ->with(['category', 'brand'])
            ->get(['id', 'name', 'category_id', 'brand_id', 'new_price', 'old_price', 'status']);

        if ($selectedProductIds->isEmpty()) {
            return $products
                ->sortBy(fn (Product $product) => mb_strtolower((string) $product->name))
                ->values();
        }

        $selectedPosition = array_flip($selectedProductIds->all());

        return $products
            ->sortBy(function (Product $product) use ($selectedPosition) {
                $productId = (int) $product->id;

                if (array_key_exists($productId, $selectedPosition)) {
                    return sprintf('0-%06d-%06d', (int) $selectedPosition[$productId], $productId);
                }

                return sprintf('1-%s-%06d', mb_strtolower((string) $product->name), $productId);
            })
            ->values();
    }

    private function activeCategories()
    {
        return Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }

    private function activeBrands()
    {
        return Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }

    private function activeShippingCharges()
    {
        return ShippingCharge::query()
            ->orderBy('id')
            ->get(['id', 'area_name', 'delivery_charge', 'status']);
    }

    private function prepareRequest(Request $request): void
    {
        $title = $request->input('title')
            ?: $request->input('landing_page_title')
            ?: $request->input('banner_title');

        $routeType = in_array($request->input('route_type'), [
            Campaign::ROUTE_STANDARD,
            Campaign::ROUTE_CUSTOM,
        ], true)
            ? $request->input('route_type')
            : Campaign::ROUTE_STANDARD;

        $request->merge([
            'title'        => $title,
            'slug'         => $request->filled('slug')
                ? Str::slug((string) $request->input('slug'))
                : null,
            'route_type'   => $routeType,
            'custom_route' => $routeType === Campaign::ROUTE_CUSTOM
                ? $this->normalizeCustomRoute($request->input('custom_route'))
                : null,
            'categories'   => $request->input('categories', $request->input('category_ids', [])),
            'brands'       => $request->input('brands', $request->input('brand_ids', [])),
            'products'     => $request->input('products', $request->input('product_ids', [])),
        ]);
    }

    private function normalizeCustomRoute(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        $value = trim($value, '/');
        $value = preg_replace('/\s+/', '-', $value) ?: '';
        $value = Str::lower($value);

        return $value !== '' ? $value : null;
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title) ?: 'campaign-' . time();

        $slug  = $baseSlug;
        $count = 1;

        while (
            Campaign::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $baseSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    private function cleanArray(?array $items): ?array
    {
        $items = collect($items ?? [])
            ->map(fn($item) => trim((string) $item))
            ->filter()
            ->values()
            ->toArray();

        return count($items) ? $items : null;
    }

    private function cleanBenefits(?array $benefits): ?array
    {
        return $this->cleanArray($benefits);
    }

    private function cleanComparison(?array $comparison): ?array
    {
        $leftTitle  = trim((string) ($comparison['left_title'] ?? 'গাছ চুইঝাল'));
        $rightTitle = trim((string) ($comparison['right_title'] ?? 'এটা চুইঝাল'));

        $left = collect($comparison['left'] ?? [])
            ->map(fn($item) => trim((string) $item))
            ->filter()
            ->values()
            ->toArray();

        $right = collect($comparison['right'] ?? [])
            ->map(fn($item) => trim((string) $item))
            ->filter()
            ->values()
            ->toArray();

        if (! count($left) && ! count($right)) {
            return null;
        }

        return [
            'left_title'  => $leftTitle ?: 'গাছ চুইঝাল',
            'right_title' => $rightTitle ?: 'এটা চুইঝাল',
            'left'        => $left,
            'right'       => $right,
        ];
    }

    private function cleanSectionTitles(?array $titles): array
    {
        return [
            'category_title'        => trim((string) ($titles['category_title'] ?? 'ক্যাটাগরি সমূহ')),
            'brand_title'           => trim((string) ($titles['brand_title'] ?? 'ব্র্যান্ড সমূহ')),
            'product_title'         => trim((string) ($titles['product_title'] ?? 'আমাদের প্রোডাক্ট')),
            'category_filter_title' => trim((string) ($titles['category_filter_title'] ?? 'ক্যাটাগরি দিয়ে ফিল্টার')),
            'brand_filter_title'    => trim((string) ($titles['brand_filter_title'] ?? 'ব্র্যান্ড দিয়ে ফিল্টার')),
            'comparison_title'      => trim((string) ($titles['comparison_title'] ?? 'চুইঝালের পার্থক্যসমূহ')),
            'service_title'         => trim((string) ($titles['service_title'] ?? 'কেন আমরাই সেরা')),
            'review_title'          => trim((string) ($titles['review_title'] ?? 'কাস্টমার রিভিউ')),
            'faq_title'             => trim((string) ($titles['faq_title'] ?? 'সচরাচর জিজ্ঞাস্য প্রশ্নাবলি')),
            'gallery_title'         => trim((string) ($titles['gallery_title'] ?? 'প্রোডাক্ট গ্যালারি')),
            'order_title'           => trim((string) ($titles['order_title'] ?? 'অর্ডার করুন এখনই')),

            'hero_star_count'       => (int) ($titles['hero_star_count'] ?? 5),
            'hero_rating_text'      => trim((string) ($titles['hero_rating_text'] ?? '৩০,০০০ হাজারও অধিক গ্রাহকের কাছে<br>আমরা হয়েছি জনপ্রিয়')),
        ];
    }

    private function cleanServiceItems(?array $serviceItems): array
    {
        $default = [
            ['icon' => 'fas fa-award', 'title' => 'অর্গানিক প্রোডাক্ট', 'description' => 'আমাদের কাছে পাবেন সেরা মানের প্রিমিয়াম পণ্য।'],
            ['icon' => 'fas fa-crown', 'title' => 'প্রিমিয়াম কোয়ালিটি', 'description' => 'সেরা কোয়ালিটির পণ্য সংগ্রহ করে সরবরাহ করা হয়।'],
            ['icon' => 'fas fa-undo-alt', 'title' => 'রিটার্ন পলিসি', 'description' => 'সমস্যা হলে সহজ রিটার্ন ও রিপ্লেসমেন্ট সুবিধা।'],
            ['icon' => 'fas fa-truck', 'title' => 'ক্যাশ অন ডেলিভারি', 'description' => 'পণ্য হাতে পেয়ে টাকা পরিশোধ করার সুবিধা।'],
        ];

        $serviceItems = $serviceItems ?: $default;

        return collect($serviceItems)
            ->map(function ($item, $index) use ($default) {
                return [
                    'icon'        => trim((string) ($item['icon'] ?? $default[$index]['icon'] ?? 'fas fa-check')),
                    'title'       => trim((string) ($item['title'] ?? $default[$index]['title'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? $default[$index]['description'] ?? '')),
                ];
            })
            ->filter(fn($item) => $item['title'] !== '')
            ->values()
            ->toArray();
    }

    private function cleanHelpContent(?array $help): array
    {
        return [
            'title'       => trim((string) ($help['title'] ?? 'সাহায্য প্রয়োজন?')),
            'description' => trim((string) ($help['description'] ?? 'যেকোনো জিজ্ঞাসা ও অর্ডারজনিত সমস্যায় কল করুন আমাদের হেল্পলাইনে অথবা নক করুন আমাদের হোয়াটসঅ্যাপ বা ফেসবুক পেজে। আমরা আছি সকাল ১০ টা থেকে রাত ৮ টা পর্যন্ত আপনার সেবায়।')),
            'button_text' => trim((string) ($help['button_text'] ?? 'হেল্পলাইন')),
        ];
    }

    private function sectionStatusData(Request $request): array
    {
        return [
            'hero_section_status'       => $request->has('hero_section_status'),
            'benefits_section_status'   => $request->has('benefits_section_status'),
            'category_section_status'   => $request->has('category_section_status'),
            'product_section_status'    => $request->has('product_section_status'),
            'comparison_section_status' => $request->has('comparison_section_status'),
            'service_section_status'    => $request->has('service_section_status'),
            'review_section_status'     => $request->has('review_section_status'),
            'gallery_section_status'    => $request->has('gallery_section_status'),
            'faq_section_status'        => $request->has('faq_section_status'),
            'help_section_status'       => $request->has('help_section_status'),
            'order_section_status'          => $request->has('order_section_status'),
            'order_tracking_section_status' => $request->has('order_tracking_section_status'),
        ];
    }

    private function campaignValidationRules(?Campaign $campaign = null): array
    {
        return [
            'title'                       => ['required', 'string', 'max:255'],

            'slug'                        => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('campaigns', 'slug')->ignore($campaign?->id),
            ],

            'route_type'                  => [
                'required',
                Rule::in([
                    Campaign::ROUTE_STANDARD,
                    Campaign::ROUTE_CUSTOM,
                ]),
            ],

            'custom_route'                => [
                'nullable',
                'required_if:route_type,' . Campaign::ROUTE_CUSTOM,
                'string',
                'max:190',
                'regex:/^' . config('campaign-routes.pattern', '[a-z0-9][a-z0-9_-]*') . '$/',
                Rule::notIn(config('campaign-routes.reserved', [])),
                Rule::unique('campaigns', 'custom_route')->ignore($campaign?->id),
            ],

            'categories'                  => ['nullable', 'array'],
            'categories.*'                => ['nullable', 'integer', 'exists:categories,id'],

            'brands'                      => ['nullable', 'array'],
            'brands.*'                    => ['nullable', 'integer', 'exists:brands,id'],

            'products'                    => ['required', 'array', 'min:1'],
            'products.*'                  => ['required', 'integer', 'exists:products,id'],

            'short_description'           => ['nullable', 'string'],
            'full_description'            => ['nullable', 'string'],
            'embed_video_url'             => ['nullable', 'url', 'max:2000'],

            'benefits_text'               => ['nullable', 'array'],
            'benefits_text.*'             => ['nullable', 'string', 'max:255'],

            'comparison_text'             => ['nullable', 'array'],
            'comparison_text.left_title'  => ['nullable', 'string', 'max:255'],
            'comparison_text.right_title' => ['nullable', 'string', 'max:255'],
            'comparison_text.left'        => ['nullable', 'array'],
            'comparison_text.left.*'      => ['nullable', 'string', 'max:255'],
            'comparison_text.right'       => ['nullable', 'array'],
            'comparison_text.right.*'     => ['nullable', 'string', 'max:255'],

            'section_titles'              => ['nullable', 'array'],
            'section_titles.*'            => ['nullable', 'string', 'max:255'],

            'service_items'               => ['nullable', 'array'],
            'service_items.*.icon'        => ['nullable', 'string', 'max:255'],
            'service_items.*.title'       => ['nullable', 'string', 'max:255'],
            'service_items.*.description' => ['nullable', 'string', 'max:500'],

            'help_content'                => ['nullable', 'array'],
            'help_content.title'          => ['nullable', 'string', 'max:255'],
            'help_content.description'    => ['nullable', 'string', 'max:1000'],
            'help_content.button_text'    => ['nullable', 'string', 'max:255'],

            'button_text'                 => ['nullable', 'string', 'max:255'],
            'order_form_title'            => ['nullable', 'string', 'max:255'],
            'order_form_subtitle'         => ['nullable', 'string', 'max:255'],
            'meta_title'                  => ['nullable', 'string', 'max:255'],
            'meta_description'            => ['nullable', 'string'],

            'banner_image'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_one'                   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_two'                   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_three'                 => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'review_image'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'campaign_video'              => ['nullable', 'file', 'mimes:mp4,webm,ogg', 'max:51200'],
            'hero_whatsapp'               => ['nullable', 'string', 'max:255'],
            'hero_phone'                  => ['nullable', 'string', 'max:255'],
            'campaign_product_gallery'    => ['nullable', 'array'],
            'campaign_product_gallery.*'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'hero_slider_images'          => ['nullable', 'array'],
            'hero_slider_images.*'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],


            'shipping_charges'                  => ['nullable', 'array'],
            'shipping_charges.*.id'             => ['nullable', 'integer', 'exists:shipping_charges,id'],
            'shipping_charges.*.area_name'      => ['nullable', 'string', 'max:255'],
            'shipping_charges.*.delivery_charge'=> ['nullable', 'numeric', 'min:0', 'max:999999'],
            'shipping_charges.*.status'         => ['nullable', 'boolean'],
            'shipping_charges.*.delete'         => ['nullable', 'boolean'],

            'campaign_faqs'                       => ['nullable', 'array'],
            'campaign_faqs.*.id'                  => ['nullable', 'integer', 'exists:faqs,id'],
            'campaign_faqs.*.question'            => ['nullable', 'string', 'max:255'],
            'campaign_faqs.*.answer'              => ['nullable', 'string'],
            'campaign_faqs.*.sort_order'          => ['nullable', 'integer', 'min:0'],
            'campaign_faqs.*.status'              => ['nullable', 'boolean'],

            'campaign_reviews'                    => ['nullable', 'array'],
            'campaign_reviews.*.id'               => ['nullable', 'integer', 'exists:reviews,id'],
            'campaign_reviews.*.customer_name'    => ['nullable', 'string', 'max:255'],
            'campaign_reviews.*.location'         => ['nullable', 'string', 'max:255'],
            'campaign_reviews.*.rating'           => ['nullable', 'integer', 'min:1', 'max:5'],
            'campaign_reviews.*.review_text'      => ['nullable', 'string'],
            'campaign_reviews.*.social_link'      => ['nullable', 'url', 'max:255'],
            'campaign_reviews.*.status'           => ['nullable', 'boolean'],
            'campaign_reviews.*.remove_image'     => ['nullable', 'boolean'],
            'campaign_reviews.*.customer_image'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function campaignData(Request $request, array $productIds, ?Campaign $campaign = null): array
    {
        return [
            'title'               => $request->title,

            'slug'                => $request->slug
                ?: $this->generateUniqueSlug($request->title, $campaign?->id),

            'route_type'          => $request->route_type,
            'custom_route'        => $request->route_type === Campaign::ROUTE_CUSTOM
                ? $request->custom_route
                : null,

            'campaign_type'       => count($productIds) > 1 ? 'multiple' : 'single',

            'short_description'   => $request->short_description,
            'full_description'    => $request->full_description,
            'offer_text'          => null,
            'embed_video_url'     => $request->embed_video_url,

            'benefits_text'       => $this->cleanBenefits($request->input('benefits_text', [])),
            'comparison_text'     => $this->cleanComparison($request->input('comparison_text', [])),
            'section_titles'      => $this->cleanSectionTitles($request->input('section_titles', [])),
            'service_items'       => $this->cleanServiceItems($request->input('service_items', [])),
            'help_content'        => $this->cleanHelpContent($request->input('help_content', [])),

            'button_text'         => $request->button_text ?: 'অর্ডার করুন',
            'order_form_title'    => $request->order_form_title,
            'order_form_subtitle' => $request->order_form_subtitle,
            'enable_bulk_order'   => $request->boolean('enable_bulk_order'),
            'hero_whatsapp'       => $request->hero_whatsapp,
            'hero_phone'          => $request->hero_phone,

            ...$this->sectionStatusData($request),

            'status'              => $request->boolean('status'),
            'meta_title'          => $request->meta_title,
            'meta_description'    => $request->meta_description,
        ];
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('custom_route', 'like', "%{$search}%")
                    ->orWhere('route_type', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('full_description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (bool) $request->status);
        }

        return $query;
    }

    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query     = $this->applyFilters($query, $request);
        $campaigns = $query->paginate(10);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Campaigns', 'url' => route('admin.campaigns.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = ['text' => 'Trash', 'url' => route('admin.campaigns.trashed')];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html'   => view('admin.campaigns.partials.table', [
                    'campaigns' => $campaigns,
                    'isTrash'   => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.campaigns.index', [
            'campaigns'  => $campaigns,
            'title'      => $title,
            'breadcrumb' => $breadcrumb,
            'isTrash'    => $isTrash,
        ]);
    }

    public function orderTrackingSettings()
    {
        $campaign = Campaign::resolveHomepageCampaign();

        return view('admin.campaigns.order-tracking-settings', [
            'campaign' => $campaign,
            'title' => 'Order Tracking Settings',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Order Tracking Settings', 'url' => route('admin.order-tracking-settings.index')],
            ],
        ]);
    }

    public function updateOrderTrackingSettings(Request $request, Campaign $campaign)
    {
        $homepageCampaign = Campaign::resolveHomepageCampaign();

        abort_unless($homepageCampaign && $homepageCampaign->is($campaign), 404);

        $validated = $request->validate([
            'order_tracking_section_status' => ['required', 'boolean'],
        ]);

        $campaign->forceFill([
            'order_tracking_section_status' => (bool) $validated['order_tracking_section_status'],
        ])->save();

        return redirect()
            ->route('admin.order-tracking-settings.index')
            ->with('success', $campaign->order_tracking_section_status
                ? 'Order Tracking section and header activated successfully.'
                : 'Order Tracking section and header deactivated successfully.');
    }

    public function index(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse($request, $this->campaignQuery(), 'Landing Page Manage');
    }

    public function create()
    {
        $this->adminOnly();

        return view('admin.campaigns.create', [
            'campaign'           => null,
            'categories'         => $this->activeCategories(),
            'brands'             => $this->activeBrands(),
            'products'           => $this->activeProducts(),
            'selectedCategories' => [],
            'selectedBrands'     => [],
            'selectedProducts'   => [],
            'campaignFaqs'       => collect(),
            'campaignReviews'    => collect(),
            'shippingCharges'    => $this->activeShippingCharges(),
            'isEdit'             => false,
            'action'             => route('admin.campaigns.store'),
            'title'              => 'Landing Page Create',
            'breadcrumb'         => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Campaigns', 'url' => route('admin.campaigns.index')],
                ['text' => 'Create Campaign', 'url' => route('admin.campaigns.create')],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();
        $this->prepareRequest($request);

        $request->validate($this->campaignValidationRules());

        return DB::transaction(function () use ($request) {
            $categoryIds = collect($request->categories)->filter()->unique()->values()->toArray();
            $brandIds    = collect($request->brands)->filter()->unique()->values()->toArray();
            $productIds  = collect($request->products)
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $campaign = Campaign::create($this->campaignData($request, $productIds));

            $this->syncCategories($campaign, $categoryIds);
            $this->syncBrands($campaign, $brandIds);
            $this->syncProducts($campaign, $productIds);
            $this->uploadCampaignMedia($campaign, $request);
            $this->syncCampaignFaqs($campaign, $request);
            $this->syncCampaignReviews($campaign, $request);
            $this->syncShippingCharges($request);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Campaign created successfully.');
        });
    }

    public function show(Campaign $campaign)
    {
        $this->adminOnly();

        $campaign->load(['products', 'categories', 'brands']);

        return view('admin.campaigns.show', [
            'campaign'   => $campaign,
            'title'      => 'Campaign Details',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Campaigns', 'url' => route('admin.campaigns.index')],
                ['text' => 'Campaign Details', 'url' => route('admin.campaigns.show', $campaign->id)],
            ],
        ]);
    }

    public function edit(Campaign $campaign)
    {
        $this->adminOnly();

        $campaign->load([
            'products',
            'categories',
            'brands',
            'faqs' => function ($query) {
                $query->orderBy('sort_order')->orderBy('id');
            },
            'reviews' => function ($query) {
                $query->latest();
            },
            'reviews.media',
        ]);

        $selectedProducts = $campaign->products
            ->sortBy(fn (Product $product) => (int) ($product->pivot->sort_order ?? 999999))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();

        return view('admin.campaigns.edit', [
            'campaign'           => $campaign,
            'categories'         => $this->activeCategories(),
            'brands'             => $this->activeBrands(),
            'products'           => $this->activeProducts($selectedProducts),
            'selectedCategories' => $campaign->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->toArray(),
            'selectedBrands'     => $campaign->brands->pluck('id')->map(fn ($id) => (int) $id)->values()->toArray(),
            'selectedProducts'   => $selectedProducts,
            'campaignFaqs'       => $campaign->faqs,
            'campaignReviews'    => $campaign->reviews,
            'shippingCharges'    => $this->activeShippingCharges(),
            'isEdit'             => true,
            'action'             => route('admin.campaigns.update', $campaign->id),
            'title'              => 'Edit Campaign',
            'breadcrumb'         => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Campaigns', 'url' => route('admin.campaigns.index')],
                ['text' => 'Edit Campaign', 'url' => route('admin.campaigns.edit', $campaign->id)],
            ],
        ]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->adminOnly();
        $this->prepareRequest($request);

        $request->validate($this->campaignValidationRules($campaign));

        return DB::transaction(function () use ($request, $campaign) {
            $categoryIds = collect($request->categories)->filter()->unique()->values()->toArray();
            $brandIds    = collect($request->brands)->filter()->unique()->values()->toArray();
            $productIds  = collect($request->products)
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $campaign->update($this->campaignData($request, $productIds, $campaign));

            $this->syncCategories($campaign, $categoryIds);
            $this->syncBrands($campaign, $brandIds);
            $this->syncProducts($campaign, $productIds);
            $this->uploadCampaignMedia($campaign, $request);
            $this->syncCampaignFaqs($campaign, $request);
            $this->syncCampaignReviews($campaign, $request);
            $this->syncShippingCharges($request);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Campaign updated successfully.');
        });
    }


    public function setDefault(Request $request, Campaign $campaign)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'is_default' => ['required', 'boolean'],
        ]);

        $campaign = Campaign::toggleDefaultCampaign(
            $campaign->id,
            (bool) $validated['is_default']
        );

        return response()->json([
            'status'     => true,
            'is_default' => $campaign->is_default,
            'message'    => $campaign->is_default
                ? 'Default campaign updated successfully.'
                : 'Default campaign removed successfully.',
        ]);
    }

    public function destroy(Campaign $campaign)
    {
        $this->adminOnly();

        DB::transaction(function () use ($campaign) {
            if ($campaign->is_default) {
                $campaign->forceFill(['is_default' => false])->save();
            }

            $campaign->delete();
        });

        return response()->json([
            'status'  => true,
            'message' => 'Campaign moved to trash successfully.',
        ]);
    }

    public function trash(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse(
            $request,
            $this->campaignQuery(true),
            'Campaign Trash Bin',
            true
        );
    }

    public function restore($id)
    {
        $this->adminOnly();

        Campaign::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status'  => true,
            'message' => 'Campaign restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();

        $campaign = Campaign::onlyTrashed()->findOrFail($id);

        foreach ([
            'banner_image',
            'image_one',
            'image_two',
            'image_three',
            'review_image',
            'campaign_video',
            'hero_slider_images',
            'campaign_product_gallery',
        ] as $collection) {
            $campaign->clearMediaCollection($collection);
        }

        $campaign->forceDelete();

        return response()->json([
            'status'  => true,
            'message' => 'Campaign permanently deleted successfully.',
        ]);
    }

    public function multipleAction(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'action' => ['required', 'in:delete,restore,force_delete,active,inactive'],
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
        ]);

        if ($request->action === 'delete') {
            DB::transaction(function () use ($request) {
                Campaign::whereIn('id', $request->ids)->update(['is_default' => false]);
                Campaign::whereIn('id', $request->ids)->delete();
            });

            return response()->json(['status' => true, 'message' => 'Selected campaigns moved to trash.']);
        }

        if ($request->action === 'restore') {
            Campaign::onlyTrashed()->whereIn('id', $request->ids)->restore();

            return response()->json(['status' => true, 'message' => 'Selected campaigns restored.']);
        }

        if ($request->action === 'force_delete') {
            $campaigns = Campaign::onlyTrashed()->whereIn('id', $request->ids)->get();

            foreach ($campaigns as $campaign) {
                foreach ([
                    'banner_image',
                    'image_one',
                    'image_two',
                    'image_three',
                    'review_image',
                    'campaign_video',
                    'hero_slider_images',
                    'campaign_product_gallery',
                ] as $collection) {
                    $campaign->clearMediaCollection($collection);
                }

                $campaign->forceDelete();
            }

            return response()->json(['status' => true, 'message' => 'Selected campaigns permanently deleted.']);
        }

        if ($request->action === 'active') {
            Campaign::whereIn('id', $request->ids)->update(['status' => true]);

            return response()->json(['status' => true, 'message' => 'Selected campaigns activated.']);
        }

        if ($request->action === 'inactive') {
            Campaign::whereIn('id', $request->ids)->update(['status' => false]);

            return response()->json(['status' => true, 'message' => 'Selected campaigns deactivated.']);
        }

        return response()->json(['status' => false, 'message' => 'Invalid bulk action selected.'], 422);
    }

    public function attachProducts(Request $request, Campaign $campaign)
    {
        $this->adminOnly();

        $request->validate([
            'products'   => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'integer', 'exists:products,id'],
        ]);

        $this->syncProducts($campaign, $request->products);

        return response()->json([
            'status'  => true,
            'message' => 'Campaign products updated successfully.',
        ]);
    }

    public function detachProduct(Campaign $campaign, Product $product)
    {
        $this->adminOnly();

        $campaign->products()->detach($product->id);

        return response()->json([
            'status'  => true,
            'message' => 'Product detached from campaign successfully.',
        ]);
    }

    public function deleteMedia($id)
    {
        $this->adminOnly();

        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::findOrFail($id);

        if ($media->model_type !== Campaign::class) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid campaign media.',
            ], 403);
        }

        $allowedCollections = [
            'banner_image',
            'image_one',
            'image_two',
            'image_three',
            'review_image',
            'campaign_video',
            'hero_slider_images',
            'campaign_product_gallery',
        ];

        if (! in_array($media->collection_name, $allowedCollections, true)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid media collection.',
            ], 422);
        }

        $media->delete();

        return response()->json([
            'status'   => true,
            'message'  => 'Campaign media deleted successfully.',
            'media_id' => $id,
        ]);
    }

    private function syncCategories(Campaign $campaign, array $categoryIds): void
    {
        $syncData = [];

        foreach ($categoryIds as $index => $categoryId) {
            $syncData[$categoryId] = ['sort_order' => $index + 1];
        }

        $campaign->categories()->sync($syncData);
    }

    private function syncBrands(Campaign $campaign, array $brandIds): void
    {
        $syncData = [];

        foreach ($brandIds as $index => $brandId) {
            $syncData[$brandId] = ['sort_order' => $index + 1];
        }

        $campaign->brands()->sync($syncData);
    }

    private function syncProducts(Campaign $campaign, array $productIds): void
    {
        $productIds = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $syncData = [];

        foreach ($productIds as $index => $productId) {
            if (! isset($products[$productId])) {
                continue;
            }

            $product = $products[$productId];

            $syncData[$product->id] = [
                'campaign_price' => $product->new_price,
                'sort_order'     => $index + 1,
                'is_default'     => $index === 0,
            ];
        }

        $campaign->products()->sync($syncData);
    }

    private function uploadCampaignMedia(Campaign $campaign, Request $request): void
    {
        $pathSlug = $this->campaignMediaPathSlug($campaign);

        $singleMediaFields = [
            'banner_image',
            'image_one',
            'image_two',
            'image_three',
            'review_image',
            'campaign_video',
        ];

        foreach ($singleMediaFields as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $campaign->clearMediaCollection($field);
            $this->ensureLandingMediaDirectory($pathSlug, $field);

            $media = $campaign
                ->addMedia($file)
                ->usingName($campaign->title . ' ' . Str::headline($field))
                ->usingFileName(
                    $this->campaignSingleMediaFileName(
                        $campaign,
                        $file,
                        $field
                    )
                )
                ->withCustomProperties(
                    $this->landingMediaCustomProperties(
                        $pathSlug,
                        $field
                    )
                )
                ->toMediaCollection($field, 'public');

            $this->assertLandingMediaStored($media);
        }

        if ($request->hasFile('hero_slider_images')) {
            $sequence = (int) $campaign->media()
                ->where('collection_name', 'hero_slider_images')
                ->count();

            foreach ((array) $request->file('hero_slider_images', []) as $image) {
                if (! $image instanceof UploadedFile || ! $image->isValid()) {
                    continue;
                }

                $sequence++;
                $this->ensureLandingMediaDirectory(
                    $pathSlug,
                    'hero_slider_images'
                );

                $media = $campaign
                    ->addMedia($image)
                    ->usingName($campaign->title . ' Hero Slider ' . $sequence)
                    ->usingFileName(
                        $this->campaignMultipleMediaFileName(
                            $campaign,
                            $image,
                            'hero-slider',
                            $sequence
                        )
                    )
                    ->withCustomProperties(
                        $this->landingMediaCustomProperties(
                            $pathSlug,
                            'hero_slider_images'
                        )
                    )
                    ->toMediaCollection('hero_slider_images', 'public');

                $this->assertLandingMediaStored($media);
            }
        }

        if ($request->hasFile('campaign_product_gallery')) {
            $sequence = (int) $campaign->media()
                ->where('collection_name', 'campaign_product_gallery')
                ->count();

            foreach ((array) $request->file('campaign_product_gallery', []) as $image) {
                if (! $image instanceof UploadedFile || ! $image->isValid()) {
                    continue;
                }

                $sequence++;
                $this->ensureLandingMediaDirectory(
                    $pathSlug,
                    'campaign_product_gallery'
                );

                $media = $campaign
                    ->addMedia($image)
                    ->usingName($campaign->title . ' Product Gallery ' . $sequence)
                    ->usingFileName(
                        $this->campaignMultipleMediaFileName(
                            $campaign,
                            $image,
                            'product-gallery',
                            $sequence
                        )
                    )
                    ->withCustomProperties(
                        $this->landingMediaCustomProperties(
                            $pathSlug,
                            'campaign_product_gallery'
                        )
                    )
                    ->toMediaCollection('campaign_product_gallery', 'public');

                $this->assertLandingMediaStored($media);
            }
        }
    }

    private function campaignMediaPathSlug(Campaign $campaign): string
    {
        $slug = Str::slug((string) ($campaign->slug ?: $campaign->title));

        if ($slug !== '') {
            return $slug;
        }

        return 'landing-' . (int) $campaign->getKey();
    }

    private function campaignSingleMediaFileName(
        Campaign $campaign,
        UploadedFile $file,
        string $field
    ): string {
        return sprintf(
            '%s-%s.%s',
            $this->campaignMediaPathSlug($campaign),
            Str::slug($field),
            $this->uploadedMediaExtension($file)
        );
    }

    private function campaignMultipleMediaFileName(
        Campaign $campaign,
        UploadedFile $file,
        string $type,
        int $sequence
    ): string {
        return sprintf(
            '%s-%s-%s-%s.%s',
            $this->campaignMediaPathSlug($campaign),
            Str::slug($type),
            str_pad((string) max(1, $sequence), 2, '0', STR_PAD_LEFT),
            Str::lower(Str::random(8)),
            $this->uploadedMediaExtension($file)
        );
    }

    private function reviewCustomerImageFileName(
        Campaign $campaign,
        Review $review,
        UploadedFile $file
    ): string {
        $customerSlug = Str::slug((string) $review->customer_name)
            ?: 'customer';

        return sprintf(
            '%s-customer-review-%d-%s.%s',
            $this->campaignMediaPathSlug($campaign),
            (int) $review->id,
            $customerSlug,
            $this->uploadedMediaExtension($file)
        );
    }

    private function uploadedMediaExtension(UploadedFile $file): string
    {
        $extension = strtolower((string) (
            $file->extension()
            ?: $file->getClientOriginalExtension()
            ?: 'jpg'
        ));

        return preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'jpg';
    }

    private function landingMediaCustomProperties(
        string $pathSlug,
        string $collection
    ): array {
        $path = LandingMediaSectionMap::forCollection($collection);

        return [
            'landing_named_path'    => true,
            'landing_path_slug'     => $pathSlug,
            'landing_section_folder'=> $path['section'],
            'landing_media_folder'  => $path['media_type'],
        ];
    }

    private function ensureLandingMediaDirectory(
        string $pathSlug,
        string $collection
    ): void {
        $path = LandingMediaSectionMap::forCollection($collection);
        $directory = sprintf(
            'landings/%s/%s',
            trim($pathSlug, '/'),
            trim((string) $path['section'], '/')
        );

        $disk = Storage::disk('public');

        if (! $disk->exists($directory) && ! $disk->makeDirectory($directory)) {
            throw new \RuntimeException(
                'Unable to create the Landing media directory: ' . $directory
            );
        }
    }

    private function assertLandingMediaStored(Media $media): void
    {
        $relativePath = $media->getPathRelativeToRoot();

        if (! Storage::disk($media->disk)->exists($relativePath)) {
            throw new \RuntimeException(
                sprintf(
                    'Landing media upload failed. Expected file was not found on disk [%s] at [%s].',
                    $media->disk,
                    $relativePath
                )
            );
        }
    }

    private function syncCampaignFaqs(Campaign $campaign, Request $request): void
    {
        $rows = collect($request->input('campaign_faqs', []));
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $question = trim((string) ($row['question'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            $faqId = ! empty($row['id']) ? (int) $row['id'] : null;

            $faq = $faqId
                ? $campaign->faqs()->whereKey($faqId)->first()
                : new Faq(['campaign_id' => $campaign->id]);

            if (! $faq) {
                continue;
            }

            $faq->fill([
                'campaign_id' => $campaign->id,
                'question' => $question,
                'answer' => $answer,
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $index,
                'status' => array_key_exists('status', $row) ? (bool) $row['status'] : true,
            ]);

            $faq->save();
            $keptIds[] = $faq->id;
        }

        $deleteQuery = $campaign->faqs();

        if (count($keptIds)) {
            $deleteQuery->whereNotIn('id', $keptIds);
        }

        $deleteQuery->get()->each(function (Faq $faq) {
            $faq->delete();
        });
    }

    private function syncCampaignReviews(Campaign $campaign, Request $request): void
    {
        $rows = collect($request->input('campaign_reviews', []));
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $customerName = trim((string) ($row['customer_name'] ?? ''));
            $reviewText = trim((string) ($row['review_text'] ?? ''));

            if ($customerName === '' && $reviewText === '') {
                continue;
            }

            $reviewId = ! empty($row['id']) ? (int) $row['id'] : null;

            $review = $reviewId
                ? $campaign->reviews()->whereKey($reviewId)->first()
                : new Review(['campaign_id' => $campaign->id]);

            if (! $review) {
                continue;
            }

            $review->fill([
                'campaign_id' => $campaign->id,
                'customer_name' => $customerName ?: 'Customer',
                'location' => trim((string) ($row['location'] ?? '')) ?: null,
                'rating' => max(1, min(5, (int) ($row['rating'] ?? 5))),
                'review_text' => $reviewText ?: null,
                'social_link' => trim((string) ($row['social_link'] ?? '')) ?: null,
                'status' => array_key_exists('status', $row) ? (bool) $row['status'] : true,
            ]);

            $review->save();

            if (! empty($row['remove_image'])) {
                $review->clearMediaCollection('review_customer_image');
            }

            $image = $request->file("campaign_reviews.$index.customer_image");

            if ($image instanceof UploadedFile && $image->isValid()) {
                $review->clearMediaCollection('review_customer_image');

                $pathSlug = $this->campaignMediaPathSlug($campaign);
                $this->ensureLandingMediaDirectory(
                    $pathSlug,
                    'review_customer_image'
                );

                $media = $review
                    ->addMedia($image)
                    ->usingName(
                        $campaign->title . ' Customer Review ' . $review->id
                    )
                    ->usingFileName(
                        $this->reviewCustomerImageFileName(
                            $campaign,
                            $review,
                            $image
                        )
                    )
                    ->withCustomProperties(
                        $this->landingMediaCustomProperties(
                            $this->campaignMediaPathSlug($campaign),
                            'review_customer_image'
                        )
                    )
                    ->toMediaCollection('review_customer_image', 'public');

                $this->assertLandingMediaStored($media);
            }

            $keptIds[] = $review->id;
        }

        $deleteQuery = $campaign->reviews();

        if (count($keptIds)) {
            $deleteQuery->whereNotIn('id', $keptIds);
        }

        $deleteQuery->get()->each(function (Review $review) {
            $review->clearMediaCollection('review_customer_image');
            $review->delete();
        });
    }


    private function syncShippingCharges(Request $request): void
    {
        /*
        |--------------------------------------------------------------------------
        | Campaign Form Shipping Charges Sync
        |--------------------------------------------------------------------------
        | এই method না থাকার কারণেই error আসছিল:
        | Method App\Http\Controllers\Admin\CampaignController::syncShippingCharges does not exist.
        |
        | Campaign create/edit form থেকে shipping_charges[] array submit হবে।
        | - id থাকলে update হবে
        | - id না থাকলে নতুন row create হবে
        | - delete = 1 হলে row delete হবে
        | - status unchecked হলে inactive হবে
        */
        if (! $request->has('shipping_charges')) {
            return;
        }

        $rows = collect($request->input('shipping_charges', []));
        $keptIds = [];

        foreach ($rows as $row) {
            $shippingChargeId = ! empty($row['id']) ? (int) $row['id'] : null;
            $shouldDelete = filter_var($row['delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($shouldDelete) {
                if ($shippingChargeId) {
                    ShippingCharge::query()->whereKey($shippingChargeId)->delete();
                }

                continue;
            }

            $areaName = trim((string) ($row['area_name'] ?? ''));
            $deliveryCharge = $row['delivery_charge'] ?? null;

            if ($areaName === '' && ($deliveryCharge === null || $deliveryCharge === '')) {
                continue;
            }

            if ($areaName === '') {
                continue;
            }

            $shippingCharge = $shippingChargeId
                ? ShippingCharge::query()->whereKey($shippingChargeId)->first()
                : new ShippingCharge();

            if (! $shippingCharge) {
                continue;
            }

            $shippingCharge->area_name = $areaName;
            $shippingCharge->delivery_charge = max(0, (float) ($deliveryCharge ?? 0));
            $shippingCharge->status = array_key_exists('status', $row)
                ? filter_var($row['status'], FILTER_VALIDATE_BOOLEAN)
                : false;

            $shippingCharge->save();

            $keptIds[] = $shippingCharge->id;
        }

        /*
         * Form থেকে কোনো existing shipping charge row remove করলে সেটাও delete হবে।
         * তবে shipping_charges key না থাকলে উপরে return করা হয়েছে, তাই accidental delete হবে না।
         */
        ShippingCharge::query()
            ->when(count($keptIds), fn ($query) => $query->whereNotIn('id', $keptIds))
            ->when(! count($keptIds), fn ($query) => $query->whereRaw('1 = 1'))
            ->delete();
    }

}
