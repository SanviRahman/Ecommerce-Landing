<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Admin-only variant editing on reviewed local/test databases or the existing
 * P58-approved production host + actual MySQL database. Never rely on env
 * alone, request parameters, or an unverified schema.
 */
final class P33CatalogAdminAccess
{
    public static function assertAllowed(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin() && auth()->user()->is_active, 403);

        if (config('app.env') === 'production') {
            // Admin maintenance must remain possible during a sales pause, but
            // the HTTPS host and *actual* database still require P58 approval.
            app(P58ProductionStorefrontAccess::class)->assertApprovedDatabase(request(), false);
        } elseif (P32LocalStorefrontAccess::matchesContext(
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
