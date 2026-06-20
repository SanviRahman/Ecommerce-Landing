<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Faq;
use App\Models\Review;
use App\Models\ShippingCharge;
use App\Models\SiteSetting;
use App\Models\SocialMedia;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function index(): Response
    {
        $siteSetting = SiteSetting::query()
            ->where('status', true)
            ->latest()
            ->first();

        /**
         * Homepage campaign resolving rule:
         * 1. Show the active campaign marked as default.
         * 2. If no default campaign is selected, fallback to the latest active campaign.
         * 3. If every campaign is inactive, render an empty homepage while keeping
         *    the physical header shell visible and hiding all header content.
         */
        $campaign = Campaign::resolveHomepageCampaign([
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

        if (! $campaign) {
            return $this->homeResponse([
                'siteSetting'               => $siteSetting,
                'campaign'                  => null,
                'categories'                => collect(),
                'brands'                    => collect(),
                'products'                  => collect(),
                'orderProducts'             => collect(),
                'reviews'                   => collect(),
                'faqs'                      => collect(),
                'socialMedias'              => collect(),
                'shippingCharges'           => collect(),
                'courierServices'           => config('couriers.list', []),
                'orderFormToken'            => null,
                'showFrontendHeader'        => true,
                'showFrontendHeaderContent' => false,
            ]);
        }

        $categories    = $campaign->categories->values();
        $brands        = $campaign->brands->values();
        $products      = $this->sortProductsBySelectedCategory($campaign);
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

        return $this->homeResponse([
            'siteSetting'               => $siteSetting,
            'campaign'                  => $campaign,
            'categories'                => $categories,
            'brands'                    => $brands,
            'products'                  => $products,
            'orderProducts'             => $orderProducts,
            'reviews'                   => $reviews,
            'faqs'                      => $faqs,
            'socialMedias'              => $socialMedias,
            'shippingCharges'           => $shippingCharges,
            'courierServices'           => config('couriers.list', []),
            'orderFormToken'            => $orderFormToken,
            'showFrontendHeader'        => true,
            'showFrontendHeaderContent' => true,
        ]);
    }

    /**
     * Prevent browser/proxy cache from preserving an old campaign state after
     * an administrator changes campaign status.
     */
    private function homeResponse(array $data): Response
    {
        return response()
            ->view('frontend.pages.home', $data)
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
     * Product order follows campaign_product.sort_order selected in admin.
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
