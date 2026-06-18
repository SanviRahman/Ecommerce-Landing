<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Faq;
use App\Models\Review;
use App\Models\ShippingCharge;
use App\Models\SiteSetting;
use App\Models\SocialMedia;
use Illuminate\Support\Str;

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

        $categories = $campaign->categories->values();
        $brands = $campaign->brands->values();
        $products = $this->sortProductsBySelectedCategory($campaign);
        $orderProducts = $products;
        $orderFormToken = $this->makeOrderFormToken();

        $reviews = Review::query()
            ->with('media')
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

        $shippingCharges = ShippingCharge::query()
            ->active()
            ->orderBy('id')
            ->get();

        return response()
            ->view('frontend.pages.home', [
                'siteSetting' => $siteSetting,
                'campaign' => $campaign,
                'categories' => $categories,
                'brands' => $brands,
                'products' => $products,
                'orderProducts' => $orderProducts,
                'reviews' => $reviews,
                'faqs' => $faqs,
                'socialMedias' => $socialMedias,
                'shippingCharges' => $shippingCharges,
                'courierServices' => config('couriers.list', []),
                'orderFormToken' => $orderFormToken,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    private function makeOrderFormToken(): string
    {
        $tokens = session()->get('campaign_order_form_tokens', []);

        if (! is_array($tokens)) {
            $tokens = [];
        }

        $token = Str::random(40);

        $tokens[] = $token;
        $tokens = array_values(array_unique(array_filter($tokens)));

        session()->put('campaign_order_form_tokens', array_slice($tokens, -10));

        return $token;
    }

    /**
     * Product order must follow the admin-selected campaign_product.sort_order.
     * Do not group by category here, because that changes the selected product sequence
     * even when category and brand sections are already sorted correctly by their pivots.
     */
    private function sortProductsBySelectedCategory(Campaign $campaign)
    {
        return $campaign->products
            ->sortBy(function ($product) {
                $productOrder = (int) ($product->pivot->sort_order ?? 999999);

                return sprintf('%06d-%06d', $productOrder, (int) $product->id);
            })
            ->values();
    }
}
