<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 0=legacy, 1=reserved, 2=cancelled/released, 3=allocated on completion.
        // No existing order is marked for deferred inventory by this migration.
        if (! Schema::hasColumn('orders', 'storefront_stock_state')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedTinyInteger('storefront_stock_state')->default(0)
                    ->after('inventory_version')->index('idx_storefront_stock_state');
            });
        }
    }

    public function down(): void
    {
        // Never erase outstanding reservations or audit of completed deferred orders.
        if (Schema::hasColumn('orders', 'storefront_stock_state')) {
            if (DB::table('orders')->where('storefront_stock_state', '<>', 0)->exists()) {
                throw new \RuntimeException('Cannot remove the storefront stock state of real orders.');
            }
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex('idx_storefront_stock_state');
                $table->dropColumn('storefront_stock_state');
            });
        }
    }
};
