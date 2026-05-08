<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Product;
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

        /*
        |--------------------------------------------------------------------------
        | Main Active Campaign
        |--------------------------------------------------------------------------
        | Homepage-e latest active campaign load hobe.
        | Campaign na thakleo category/brand/product show hobe.
        */
        $campaign = Campaign::query()
            ->where('status', true)
            ->with([
                'products' => function ($query) {
                    $query->where('products.status', true)
                        ->orderByPivot('sort_order');
                },
            ])
            ->latest()
            ->first();

        /*
        |--------------------------------------------------------------------------
        | All Active Categories / Brands / Products
        |--------------------------------------------------------------------------
        | User panel-e all category, all brand, all product show hobe.
        */
        $categories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->with(['category', 'brand'])
            ->where('status', true)
            ->latest()
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Order Products
        |--------------------------------------------------------------------------
        | Customer jeno all active product theke multiple product select kore order korte pare.
        */
        $orderProducts = $products;

        $reviews = Review::query()
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
            'siteSetting'     => $siteSetting,
            'campaign'        => $campaign,
            'categories'      => $categories,
            'brands'          => $brands,
            'products'        => $products,
            'orderProducts'   => $orderProducts,
            'reviews'         => $reviews,
            'faqs'            => $faqs,
            'socialMedias'    => $socialMedias,
            'courierServices' => config('couriers.list', []),
        ]);
    }
}