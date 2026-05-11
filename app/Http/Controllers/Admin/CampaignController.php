<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
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
        $query = $trash
            ? Campaign::onlyTrashed()
            : Campaign::query();

        return $query->with(['products'])->latest();
    }

    private function activeProducts()
    {
        return Product::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'new_price', 'old_price', 'status']);
    }

    private function prepareRequest(Request $request): void
    {
        $title = $request->input('title')
            ?: $request->input('landing_page_title')
            ?: $request->input('banner_title');

        $products = $request->input('products')
            ?: $request->input('product_ids')
            ?: [];

        $request->merge([
            'title'    => $title,
            'products' => $products,
        ]);
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);

        if (! $baseSlug) {
            $baseSlug = 'campaign-' . time();
        }

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
        $query = $this->applyFilters($query, $request);

        $campaigns = $query->paginate(10);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Campaigns', 'url' => route('admin.campaigns.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url'  => route('admin.campaigns.trashed'),
            ];
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

        return $this->listResponse(
            $request,
            $this->campaignQuery(),
            'Landing Page Manage'
        );
    }

    public function create()
    {
        $this->adminOnly();

        return view('admin.campaigns.create', [
            'campaign'         => null,
            'products'         => $this->activeProducts(),
            'selectedProducts' => [],
            'isEdit'           => false,
            'action'           => route('admin.campaigns.store'),
            'title'            => 'Landing Page Create',
            'breadcrumb'       => [
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

        $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'products'            => ['required', 'array', 'min:1'],
            'products.*'          => ['required', 'integer', 'exists:products,id'],

            'short_description'   => ['nullable', 'string'],
            'full_description'    => ['nullable', 'string'],
            'offer_text'          => ['nullable', 'string', 'max:255'],
            'old_price'           => ['nullable', 'integer', 'min:0'],
            'new_price'           => ['nullable', 'integer', 'min:0'],
            'button_text'         => ['nullable', 'string', 'max:255'],
            'order_form_title'    => ['nullable', 'string', 'max:255'],
            'order_form_subtitle' => ['nullable', 'string', 'max:255'],
            'meta_title'          => ['nullable', 'string', 'max:255'],
            'meta_description'    => ['nullable', 'string'],

            'banner_image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_one'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_two'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_three'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'review_image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            // Hero video
            'campaign_video'      => ['nullable', 'file', 'mimes:mp4,webm,ogg', 'max:51200'],
        ]);

        return DB::transaction(function () use ($request) {
            $productIds = collect($request->products)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $campaign = Campaign::create([
                'title'               => $request->title,
                'slug'                => $this->generateUniqueSlug($request->title),
                'campaign_type'       => count($productIds) > 1 ? 'multiple' : 'single',
                'short_description'   => $request->short_description,
                'full_description'    => $request->full_description,
                'offer_text'          => $request->offer_text ?: $request->input('banner_title'),
                'benefits_text'       => $request->benefits_text ?: null,
                'comparison_text'     => $request->comparison_text ?: null,
                'old_price'           => $request->old_price,
                'new_price'           => $request->new_price,
                'button_text'         => $request->button_text ?: 'অর্ডার করুন',
                'order_form_title'    => $request->order_form_title,
                'order_form_subtitle' => $request->order_form_subtitle,
                'enable_bulk_order'   => $request->boolean('enable_bulk_order'),
                'status'              => $request->has('status') ? $request->boolean('status') : true,
                'meta_title'          => $request->meta_title,
                'meta_description'    => $request->meta_description,
            ]);

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

        $campaign->load(['products']);

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

        $campaign->load(['products']);

        return view('admin.campaigns.edit', [
            'campaign'         => $campaign,
            'products'         => $this->activeProducts(),
            'selectedProducts' => $campaign->products()->pluck('products.id')->toArray(),
            'isEdit'           => true,
            'action'           => route('admin.campaigns.update', $campaign->id),
            'title'            => 'Edit Campaign',
            'breadcrumb'       => [
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

        $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'slug'                => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('campaigns', 'slug')->ignore($campaign->id),
            ],
            'products'            => ['required', 'array', 'min:1'],
            'products.*'          => ['required', 'integer', 'exists:products,id'],

            'short_description'   => ['nullable', 'string'],
            'full_description'    => ['nullable', 'string'],
            'offer_text'          => ['nullable', 'string', 'max:255'],
            'old_price'           => ['nullable', 'integer', 'min:0'],
            'new_price'           => ['nullable', 'integer', 'min:0'],
            'button_text'         => ['nullable', 'string', 'max:255'],
            'order_form_title'    => ['nullable', 'string', 'max:255'],
            'order_form_subtitle' => ['nullable', 'string', 'max:255'],
            'meta_title'          => ['nullable', 'string', 'max:255'],
            'meta_description'    => ['nullable', 'string'],

            'banner_image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_one'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_two'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_three'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'review_image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            // Hero video
            'campaign_video'      => ['nullable', 'file', 'mimes:mp4,webm,ogg', 'max:51200'],
        ]);

        return DB::transaction(function () use ($request, $campaign) {
            $productIds = collect($request->products)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $campaign->update([
                'title'               => $request->title,
                'slug'                => $request->slug
                    ? Str::slug($request->slug)
                    : $this->generateUniqueSlug($request->title, $campaign->id),
                'campaign_type'       => count($productIds) > 1 ? 'multiple' : 'single',
                'short_description'   => $request->short_description,
                'full_description'    => $request->full_description,
                'offer_text'          => $request->offer_text ?: $request->input('banner_title'),
                'benefits_text'       => $request->benefits_text ?: null,
                'comparison_text'     => $request->comparison_text ?: null,
                'old_price'           => $request->old_price,
                'new_price'           => $request->new_price,
                'button_text'         => $request->button_text ?: 'অর্ডার করুন',
                'order_form_title'    => $request->order_form_title,
                'order_form_subtitle' => $request->order_form_subtitle,
                'enable_bulk_order'   => $request->boolean('enable_bulk_order'),
                'status'              => $request->has('status') ? $request->boolean('status') : true,
                'meta_title'          => $request->meta_title,
                'meta_description'    => $request->meta_description,
            ]);

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

        $campaign->clearMediaCollection('banner_image');
        $campaign->clearMediaCollection('image_one');
        $campaign->clearMediaCollection('image_two');
        $campaign->clearMediaCollection('image_three');
        $campaign->clearMediaCollection('review_image');
        $campaign->clearMediaCollection('campaign_video');

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

            return response()->json([
                'status'  => true,
                'message' => 'Selected campaigns moved to trash.',
            ]);
        }

        if ($request->action === 'restore') {
            Campaign::onlyTrashed()->whereIn('id', $request->ids)->restore();

            return response()->json([
                'status'  => true,
                'message' => 'Selected campaigns restored.',
            ]);
        }

        if ($request->action === 'force_delete') {
            $campaigns = Campaign::onlyTrashed()->whereIn('id', $request->ids)->get();

            foreach ($campaigns as $campaign) {
                $campaign->clearMediaCollection('banner_image');
                $campaign->clearMediaCollection('image_one');
                $campaign->clearMediaCollection('image_two');
                $campaign->clearMediaCollection('image_three');
                $campaign->clearMediaCollection('review_image');
                $campaign->clearMediaCollection('campaign_video');
                $campaign->forceDelete();
            }

            return response()->json([
                'status'  => true,
                'message' => 'Selected campaigns permanently deleted.',
            ]);
        }

        if ($request->action === 'active') {
            Campaign::whereIn('id', $request->ids)->update(['status' => true]);

            return response()->json([
                'status'  => true,
                'message' => 'Selected campaigns activated.',
            ]);
        }

        if ($request->action === 'inactive') {
            Campaign::whereIn('id', $request->ids)->update(['status' => false]);

            return response()->json([
                'status'  => true,
                'message' => 'Selected campaigns deactivated.',
            ]);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Invalid bulk action selected.',
        ], 422);
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
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::findOrFail($id);
        $media->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Campaign media deleted successfully.',
        ]);
    }

    private function syncProducts(Campaign $campaign, array $productIds): void
    {
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get();

        $syncData = [];

        foreach ($products as $index => $product) {
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
        $mediaFields = [
            'banner_image',
            'image_one',
            'image_two',
            'image_three',
            'review_image',
            'campaign_video',
        ];

        foreach ($mediaFields as $field) {
            if ($request->hasFile($field)) {
                $campaign->clearMediaCollection($field);
                $campaign->addMediaFromRequest($field)->toMediaCollection($field);
            }
        }
    }
}
