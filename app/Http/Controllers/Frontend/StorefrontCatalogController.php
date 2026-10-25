<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\Storefront\HomepageBuilder;
use App\Services\Storefront\PublicCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Separate staged, read-only P07 public pages; never handles orders or carts. */
final class StorefrontCatalogController extends Controller
{
    public function home(PublicCatalog $catalog, HomepageBuilder $builder): Response
    {
        $catalog->assertAvailable();
        $page = DB::table('storefront_pages')->where('slug', HomepageBuilder::PAGE_SLUG)
            ->where('mode', 'storefront')->first();
        if (! $page || ! $page->published_revision_id) {
            // Safe public fallback; NEVER read the draft just to fill an empty home.
            return $this->noStore(response()->view('storefront.pages.empty-home', [
                'navigation' => $catalog->navigationCategories(),
            ]));
        }
        $revision = $builder->revision((int) $page->published_revision_id);
        abort_unless($revision['status'] === 'published', 404);

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
            ->whereIn('id', array_unique($reviewIds))->get()->keyBy('id');
        $media = Media::query()->whereIn('id', array_unique($mediaIds))
            ->where('disk', 'public')->whereIn('collection_name', HomepageBuilder::PUBLIC_IMAGE_COLLECTIONS)
            ->get()->filter(static fn (Media $item) => is_file($item->getPath()))->keyBy('id');

        return $this->noStore(response()->view('storefront.pages.home', [
            'revision' => $revision, 'products' => $products, 'categories' => $categories,
            'reviews' => $reviews, 'media' => $media, 'isPreview' => false,
            'catalog' => $catalog, 'navigation' => $catalog->navigationCategories(),
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
        return $this->noStore(response()->view('storefront.pages.shop', [
            'products' => $products, 'navigation' => $catalog->navigationCategories(),
            'catalog' => $catalog, 'q' => $q, 'sort' => $sort, 'category' => null,
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
        return $this->noStore(response()->view('storefront.pages.shop', [
            'products' => $products, 'navigation' => $catalog->navigationCategories(),
            'catalog' => $catalog, 'q' => '', 'sort' => $sort, 'category' => $category,
        ]));
    }

    public function product(string $slug, PublicCatalog $catalog): Response
    {
        $catalog->assertAvailable();
        $product = $catalog->product($slug);
        return $this->noStore(response()->view('storefront.pages.product', [
            'product' => $product, 'category' => $product->category,
            'catalog' => $catalog, 'selection' => $catalog->selectionData($product),
            'navigation' => $catalog->navigationCategories(),
        ]));
    }

    private function noStore(Response $response): Response
    {
        return $response->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'same-origin');
    }
}
