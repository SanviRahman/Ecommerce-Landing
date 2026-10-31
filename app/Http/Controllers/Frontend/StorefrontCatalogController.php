<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\StorefrontFaq;
use App\Support\StorefrontContactLinks;
use App\Services\Storefront\HomepageBuilder;
use App\Services\Storefront\CategoryShelf;
use App\Services\Storefront\PublicCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Public catalog and published storefront pages; never directly writes orders or carts. */
final class StorefrontCatalogController extends Controller
{
    public function home(PublicCatalog $catalog, HomepageBuilder $builder, CategoryShelf $shelves): Response
    {
        $catalog->assertAvailable();
        $revision = $builder->publishedRevision();
        if ($revision === null) {
            // The draft is never a public fallback.
            return $this->noStore(response()->view('storefront.pages.empty-home', [
                'navigation' => $catalog->navigationCategories(),
            ]));
        }

        $productIds = $categoryIds = $reviewIds = $mediaIds = [];
        foreach ($revision['sections'] as $section) {
            if (! $section['is_enabled']) {
                continue;
            }
            $productIds = array_merge($productIds, $section['products']);
            $categoryIds = array_merge($categoryIds, $section['categories']);
            foreach ($section['items'] as $item) {
                if (! $item['is_enabled']) continue;
                if ($item['review_id']) $reviewIds[] = (int) $item['review_id'];
                if ($item['media_id']) $mediaIds[] = (int) $item['media_id'];
            }
        }
        $products = $catalog->eligibleProducts()->with('media', 'variants.optionValues.option')
            ->whereIn('products.id', array_unique($productIds))->get()->keyBy('id');
        $categories = $catalog->eligibleCategories()->with('media')
            ->whereIn('id', array_unique($categoryIds))->get()->keyBy('id');
        $reviews = Review::query()->where('status', true)->whereNull('campaign_id')
            ->with('media')->whereIn('id', array_unique($reviewIds))->get()->keyBy('id');
        $media = Media::query()->whereIn('id', array_unique($mediaIds))
            ->where('disk', 'public')->whereIn('collection_name', HomepageBuilder::PUBLIC_IMAGE_COLLECTIONS)
            ->get()->filter(static fn (Media $item) => is_file($item->getPath()))->keyBy('id');

        $categoryShelves = [];
        foreach ($revision['sections'] as $section) {
            if ($section['type'] === 'category_products' && $section['is_enabled']) {
                $categoryShelves[$section['section_key']] = $shelves->productsFor($section);
            }
        }
        $cartVersion = $this->cartVersion(request());
        return $this->noStore(response()->view('storefront.pages.home', [
            'revision' => $revision, 'products' => $products, 'categories' => $categories,
            'reviews' => $reviews,
            'media' => $media, 'isPreview' => false,
            'catalog' => $catalog, 'navigation' => $catalog->navigationCategories(),
            'categoryShelves' => $categoryShelves, 'cartVersion' => $cartVersion,
        ]));
    }

    /** Separate public storefront FAQ table: campaign-owned FAQs stay unchanged. */
    public function help(PublicCatalog $catalog): Response
    {
        $catalog->assertAvailable();
        $faqs = StorefrontFaq::query()->published()->limit(40)->get();
        return $this->noStore(response()->view('storefront.pages.help', [
            'navigation' => $catalog->navigationCategories(), 'faqs' => $faqs,
        ]));
    }

    /** Contact links come only from validated existing site settings. */
    public function contact(PublicCatalog $catalog, StorefrontContactLinks $contacts): Response
    {
        $catalog->assertAvailable();
        return $this->noStore(response()->view('storefront.pages.contact', [
            'navigation' => $catalog->navigationCategories(), 'contacts' => $contacts->active(),
            'contactDetails' => app(\App\Support\StorefrontContactDetails::class)->get(),
        ]));
    }

    public function shop(Request $request, PublicCatalog $catalog): Response
    {
        $catalog->assertAvailable();
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,name'],
        ]);
        $q = trim($data['q'] ?? '');
        $sort = $data['sort'] ?? 'newest';
        $products = $catalog->sortedProducts($catalog->eligibleProducts()
            ->when($q !== '', static function ($builder) use ($q): void {
                $builder->where('products.name', 'like', '%' . addcslashes($q, '%_\\') . '%');
            }), $sort)->paginate(16)->withQueryString();
        $cartVersion = $this->cartVersion($request);
        return $this->noStore(response()->view('storefront.pages.shop', [
            'products' => $products, 'navigation' => $catalog->navigationCategories(),
            'catalog' => $catalog, 'q' => $q, 'sort' => $sort, 'category' => null,
            'cartVersion' => $cartVersion,
        ]));
    }

    public function category(Request $request, string $slug, PublicCatalog $catalog): Response
    {
        $catalog->assertAvailable();
        $data = $request->validate(['sort' => ['nullable', 'in:newest,price_asc,price_desc,name']]);
        $category = $catalog->eligibleCategories()->with(['media', 'parent'])->where('slug', $slug)->firstOrFail();
        $categoryIds = [$category->id];
        if ($category->parent_id === null) {
            $children = $catalog->eligibleCategories()->where('parent_id', $category->id)->pluck('id')->all();
            $categoryIds = array_merge($categoryIds, $children);
        }
        $sort = $data['sort'] ?? 'newest';
        $products = $catalog->sortedProducts($catalog->eligibleProducts()
            ->whereIn('products.category_id', $categoryIds), $sort)->paginate(16)->withQueryString();
        $cartVersion = $this->cartVersion($request);
        return $this->noStore(response()->view('storefront.pages.shop', [
            'products' => $products, 'navigation' => $catalog->navigationCategories(),
            'catalog' => $catalog, 'q' => '', 'sort' => $sort, 'category' => $category,
            'cartVersion' => $cartVersion,
        ]));
    }

    public function product(Request $request, string $slug, PublicCatalog $catalog): Response
    {
        $catalog->assertAvailable();
        $product = $catalog->product($slug);
        $cartVersion = null;
        if (($request->attributes->get('p26_isolated_commerce_qa', false) === true
                || $request->attributes->get('p32_local_commerce', false) === true
                || $request->attributes->get('p58_production_commerce') === true)
            && config('storefront.p08_cart_test_enabled', false) === true) {
            $ownerHash = app(\App\Services\Cart\GuestCartOwner::class)->hash($request);
            $cartVersion = app(\App\Services\Cart\GuestCartService::class)->show($ownerHash)['version'];
        }
        // Catalog-only recommendations: never include inactive, deleted or hidden-category
        // products; prefer this product's category, then use general collection items.
        $relatedProducts = $catalog->sortedProducts($catalog->eligibleProducts()
            ->where('products.id', '<>', $product->id)
            ->where('products.category_id', $product->category_id), 'newest')
            ->limit(8)->get();
        if ($relatedProducts->count() < 4) {
            $additional = $catalog->sortedProducts($catalog->eligibleProducts()
                ->where('products.id', '<>', $product->id)
                ->whereNotIn('products.id', $relatedProducts->pluck('id')->all()), 'newest')
                ->limit(8 - $relatedProducts->count())->get();
            $relatedProducts = $relatedProducts->concat($additional);
        }
        return $this->noStore(response()->view('storefront.pages.product', [
            'product' => $product, 'category' => $product->category,
            'catalog' => $catalog, 'selection' => $catalog->selectionData($product),
            'cartVersion' => $cartVersion, 'relatedProducts' => $relatedProducts,
            'navigation' => $catalog->navigationCategories(),
        ]));
    }

    private function cartVersion(Request $request): ?int
    {
        // Ordinary storefront GETs must NEVER read the isolated QA cart or owner.
        if (($request->attributes->get('p26_isolated_commerce_qa', false) !== true
                && $request->attributes->get('p32_local_commerce', false) !== true
                && $request->attributes->get('p58_production_commerce') !== true)
            || config('storefront.p08_cart_test_enabled', false) !== true) return null;
        $owner = app(\App\Services\Cart\GuestCartOwner::class);
        return (int) app(\App\Services\Cart\GuestCartService::class)
            ->show($owner->hash($request))['version'];
    }

    private function noStore(Response $response): Response
    {
        return $response->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'same-origin');
    }
}
