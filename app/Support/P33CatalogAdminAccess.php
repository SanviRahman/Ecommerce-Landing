<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Admin-only catalog editing on the original isolated QA DB or verified 8000 local main DB. */
final class P33CatalogAdminAccess
{
    public static function assertAllowed(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin() && auth()->user()->is_active, 403);

        if (P32LocalStorefrontAccess::matchesContext(
            (string) config('app.env'), config('app.debug') === true,
            (string) request()->ip(), (string) request()->getHost(),
            (int) request()->getPort()
        )) {
            app(P32LocalStorefrontAccess::class)->assertActualMainDatabase();
        } else {
            P02MigrationSafety::assertIsolatedTestDatabase();
        }

        foreach ([
            'product_options', 'product_option_values', 'product_variants',
            'product_variant_values', 'product_option_assignments',
            'inventory_movements', 'categories', 'products',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Catalog schema is incomplete: ' . $table);
            }
        }
    }
}
