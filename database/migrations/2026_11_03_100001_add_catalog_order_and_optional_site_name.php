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
        // Existing names, logos and orders are not touched; blank names may now be saved as NULL.
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('website_name')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table): void {
            // Zero puts not-yet-arranged/new products first, newest first within ties.
            // Product ordering is independent of inventory, pricing and campaigns.
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['category_id', 'sort_order', 'id'], 'products_category_merch_sort_idx');
        });
    }

    public function down(): void
    {
        // Do not silently replace a deliberately blank name or lose a chosen product order.
        if (DB::table('site_settings')->whereNull('website_name')->exists()) {
            throw new RuntimeException('Cannot restore required website_name while NULL values exist. Set names before rollback.');
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_category_merch_sort_idx');
            $table->dropColumn('sort_order');
        });
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('website_name')->nullable(false)->change();
        });
    }
};
