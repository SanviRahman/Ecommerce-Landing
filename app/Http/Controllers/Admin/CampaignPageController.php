<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Faq;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\SocialMedia;

class CampaignPageController extends Controller
{
    public function show(Campaign $campaign)
    {
        abort_if(! $campaign->status, 404);

        $campaign->load([
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
        ]);

        $siteSetting = SiteSetting::query()
            ->where('status', true)
            ->latest()
            ->first();

        $products = $campaign->products->values();

        $categories = $campaign->categories->filter(function ($category) use ($products) {
            return $products->pluck('category_id')->contains($category->id);
        })->values();

        $brands = $campaign->brands->filter(function ($brand) use ($products) {
            return $products->pluck('brand_id')->contains($brand->id);
        })->values();

        $orderProducts = $products;

        $reviews = Review::query()
            ->where('status', true)
            ->where(function ($query) use ($campaign) {
                $query->whereNull('campaign_id')
                    ->orWhere('campaign_id', $campaign->id);
            })
            ->latest()
            ->take(12)
            ->get();

        $faqs = Faq::query()
            ->where('status', true)
            ->where(function ($query) use ($campaign) {
                $query->whereNull('campaign_id')
                    ->orWhere('campaign_id', $campaign->id);
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
}