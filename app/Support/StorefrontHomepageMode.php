<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Storefront\HomepageBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Root-page display choice. Reuses the existing storefront_pages.mode column. */
final class StorefrontHomepageMode
{
    public const STOREFRONT = 'storefront';
    public const CAMPAIGN = 'campaign';

    /** A missing page retains the previous default: public storefront. */
    public static function normalize(?string $storedMode): string
    {
        return $storedMode === self::CAMPAIGN ? self::CAMPAIGN : self::STOREFRONT;
    }

    public function current(): string
    {
        // Campaign Management must remain available before a storefront schema deploy.
        if (! Schema::hasTable('storefront_pages')) {
            return self::STOREFRONT;
        }

        $storedMode = DB::table('storefront_pages')
            ->where('slug', HomepageBuilder::PAGE_SLUG)
            ->value('mode');

        return self::normalize($storedMode);
    }
}
