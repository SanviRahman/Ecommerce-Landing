<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Faq;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\SocialMedia;

class HomeController extends Controller
{
    public function index()
    {
        $siteSetting = SiteSetting::query()
            ->where('status', true)
            ->latest()
            ->first();

        $campaign = Campaign::query()
            ->where('status', true)
            ->with([
                'categories' => function ($query) {
                    $query->where('categories.status', true)
                        ->orderByPivot('sort_order');
                },
                'brands' => function ($query) {
                    $query->where('brands.status', true)
                        ->orderByPivot('sort_order');
                },
                'products' => function ($query) {
                    $query->with(['category', 'brand'])
                        ->where('products.status', true)
                        ->orderByPivot('sort_order');
                },
            ])
            ->latest()
            ->first();

        $categories = $campaign ? $campaign->categories->values() : collect();
        $brands = $campaign ? $campaign->brands->values() : collect();
        $products = $campaign ? $this->sortProductsBySelectedCategory($campaign) : collect();
        $orderProducts = $products;

        $reviews = Review::query()
            ->with('media')
            ->where('status', true)
            ->when($campaign, function ($query) use ($campaign) {
                $query->where(function ($q) use ($campaign) {
                    $q->whereNull('campaign_id')
                        ->orWhere('campaign_id', $campaign->id);
                });
            })
            ->latest()
            ->take(12)
            ->get();

        $faqs = Faq::query()
            ->where('status', true)
            ->when($campaign, function ($query) use ($campaign) {
                $query->where(function ($q) use ($campaign) {
                    $q->whereNull('campaign_id')
                        ->orWhere('campaign_id', $campaign->id);
                });
            })
            ->orderBy('sort_order')
            ->latest()
            ->take(10)
            ->get();

        $socialMedias = SocialMedia::query()
            ->where('status', true)
            ->latest()
            ->get();

        return view('frontend.pages.home', [
            'siteSetting' => $siteSetting,
            'campaign' => $campaign,
            'categories' => $categories,
            'brands' => $brands,
            'products' => $products,
            'orderProducts' => $orderProducts,
            'reviews' => $reviews,
            'faqs' => $faqs,
            'socialMedias' => $socialMedias,
            'courierServices' => config('couriers.list', []),
        ]);
    }

    private function sortProductsBySelectedCategory(Campaign $campaign)
    {
        $categoryOrder = $campaign->categories->pluck('id')->flip();

        return $campaign->products
            ->sortBy(function ($product) use ($categoryOrder) {
                $categoryIndex = $categoryOrder->has($product->category_id)
                    ? (int) $categoryOrder[$product->category_id]
                    : 999999;

                $productOrder = (int) ($product->pivot->sort_order ?? 999999);

                return sprintf('%06d-%06d-%06d', $categoryIndex, $productOrder, $product->id);
            })
            ->values();
    }
}
