<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

        return $query->with(['products', 'categories', 'brands'])->latest();
    }

    private function activeProducts()
    {
        return Product::query()
            ->active()
            ->with(['category', 'brand'])
            ->orderBy('name')
            ->get(['id', 'name', 'category_id', 'brand_id', 'new_price', 'old_price', 'status']);
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

    private function prepareRequest(Request $request): void
    {
        $title = $request->input('title')
            ?: $request->input('landing_page_title')
            ?: $request->input('banner_title');

        $request->merge([
            'title'      => $title,
            'categories' => $request->input('categories', $request->input('category_ids', [])),
            'brands'     => $request->input('brands', $request->input('brand_ids', [])),
            'products'   => $request->input('products', $request->input('product_ids', [])),
        ]);
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
            'order_section_status'      => $request->has('order_section_status'),
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
                Rule::unique('campaigns', 'slug')->ignore($campaign?->id),
            ],

            'categories'                  => ['nullable', 'array'],
            'categories.*'                => ['nullable', 'integer', 'exists:categories,id'],

            'brands'                      => ['nullable', 'array'],
            'brands.*'                    => ['nullable', 'integer', 'exists:brands,id'],

            'products'                    => ['required', 'array', 'min:1'],
            'products.*'                  => ['required', 'integer', 'exists:products,id'],

            'short_description'           => ['nullable', 'string'],
            'full_description'            => ['nullable', 'string'],
            'offer_text'                  => ['nullable', 'string', 'max:255'],
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
        ];
    }

    private function campaignData(Request $request, array $productIds, ?Campaign $campaign = null): array
    {
        return [
            'title'               => $request->title,

            'slug'                => $campaign
                ? ($request->slug ? Str::slug($request->slug) : $this->generateUniqueSlug($request->title, $campaign->id))
                : $this->generateUniqueSlug($request->title),

            'campaign_type'       => count($productIds) > 1 ? 'multiple' : 'single',

            'short_description'   => $request->short_description,
            'full_description'    => $request->full_description,
            'offer_text'          => $request->offer_text ?: $request->input('banner_title'),
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

            'status'              => $request->has('status') ? $request->boolean('status') : true,
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
            $productIds  = collect($request->products)->filter()->unique()->values()->toArray();

            $campaign = Campaign::create($this->campaignData($request, $productIds));

            $this->syncCategories($campaign, $categoryIds);
            $this->syncBrands($campaign, $brandIds);
            $this->syncProducts($campaign, $productIds);
            $this->uploadCampaignMedia($campaign, $request);

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

        $campaign->load(['products', 'categories', 'brands']);

        return view('admin.campaigns.edit', [
            'campaign'           => $campaign,
            'categories'         => $this->activeCategories(),
            'brands'             => $this->activeBrands(),
            'products'           => $this->activeProducts(),
            'selectedCategories' => $campaign->categories()->pluck('categories.id')->toArray(),
            'selectedBrands'     => $campaign->brands()->pluck('brands.id')->toArray(),
            'selectedProducts'   => $campaign->products()->pluck('products.id')->toArray(),
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
            $productIds  = collect($request->products)->filter()->unique()->values()->toArray();

            $campaign->update($this->campaignData($request, $productIds, $campaign));

            $this->syncCategories($campaign, $categoryIds);
            $this->syncBrands($campaign, $brandIds);
            $this->syncProducts($campaign, $productIds);
            $this->uploadCampaignMedia($campaign, $request);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Campaign updated successfully.');
        });
    }

    public function destroy(Campaign $campaign)
    {
        $this->adminOnly();

        $campaign->delete();

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
            Campaign::whereIn('id', $request->ids)->delete();

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
        $singleMediaFields = [
            'banner_image',
            'image_one',
            'image_two',
            'image_three',
            'review_image',
            'campaign_video',
        ];

        foreach ($singleMediaFields as $field) {
            if ($request->hasFile($field)) {
                $campaign->clearMediaCollection($field);
                $campaign->addMediaFromRequest($field)->toMediaCollection($field);
            }
        }

        // Hero multiple slider images.
        // Existing hero slider images will stay; newly selected images will be added.
        if ($request->hasFile('hero_slider_images')) {
            foreach ($request->file('hero_slider_images') as $image) {
                if ($image && $image->isValid()) {
                    $campaign
                        ->addMedia($image)
                        ->toMediaCollection('hero_slider_images');
                }
            }
        }

        // Campaign product gallery images.
        // Existing gallery images will stay; newly selected images will be added.
        if ($request->hasFile('campaign_product_gallery')) {
            foreach ($request->file('campaign_product_gallery') as $image) {
                if ($image && $image->isValid()) {
                    $campaign
                        ->addMedia($image)
                        ->toMediaCollection('campaign_product_gallery');
                }
            }
        }
    }
}
