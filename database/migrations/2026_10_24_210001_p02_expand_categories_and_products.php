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

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['parent_id', 'status', 'sort_order', 'id'], 'cat_parent_status_sort_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_variants')->default(false);
            $table->unsignedSmallInteger('inventory_version')->default(0);
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['has_variants', 'inventory_version']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('cat_parent_status_sort_idx');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('sort_order');
        });
    }
};
