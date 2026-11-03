<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Storefront\CategoryShelf;
use App\Services\Storefront\HomepageBuilder;
use App\Services\Storefront\PublicCatalog;
use App\Support\StorefrontHomepageMode;
use Illuminate\Http\Response;

/** Keep / unchanged while switching between the published homepage and the default landing. */
final class RootHomepageController extends Controller
{
    public function __invoke(
        StorefrontHomepageMode $mode,
        HomeController $campaignHome,
        StorefrontCatalogController $storefrontHome,
        PublicCatalog $catalog,
        HomepageBuilder $builder,
        CategoryShelf $shelves,
    ): Response {
        if ($mode->current() === StorefrontHomepageMode::CAMPAIGN) {
            // Root must never silently show another campaign when the admin
            // explicitly disabled the default. Explicit /campaign/{slug} URLs
            // remain independent of this root-page display choice.
            if (! Campaign::query()->active()->default()->exists()) {
                return response()->view('frontend.pages.website-off', [], 503)
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }
            return $campaignHome->index();
        }

        // Keep the existing storefront renderer, guards and published-only policy.
        return $storefrontHome->home($catalog, $builder, $shelves);
    }
}
