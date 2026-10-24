<?php

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing integer values are WHOLE BDT, not minor units.
     * Changing to DECIMAL preserves numeric 999 as 999.00.
     * Review live column types, constraints and backups before applying.
     */
    public function up(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::table('orders', function (Blueprint $table) {
            foreach (['sub_total', 'shipping_charge', 'cod_charge', 'total_amount'] as $column) {
                $table->decimal($column, 14, 2)->default(0)->change();
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            foreach (['unit_price', 'total_price', 'discount_amount'] as $column) {
                $table->decimal($column, 14, 2)->default(0)->change();
            }
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        // Never silently round, truncate or narrow real order money on rollback.
        throw new RuntimeException('Money widening is forward-only. Restore an approved backup or write a reviewed forward migration.');
    }
};
