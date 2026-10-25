<?php

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        P02MigrationSafety::assertApprovedP02Up();

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('inventory_version')->default(0);
            $table->index(['inventory_version', 'order_status'], 'orders_inventory_status_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('variant_sku', 100)->nullable();
            $table->json('variant_options')->nullable();
            $table->unsignedInteger('inventory_allocated_quantity')->default(0);
            $table->index(['product_variant_id', 'order_id'], 'order_items_variant_order_idx');
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_variant_order_idx');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn(['variant_sku', 'variant_options', 'inventory_allocated_quantity']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_inventory_status_idx');
            $table->dropColumn('inventory_version');
        });
    }
};
