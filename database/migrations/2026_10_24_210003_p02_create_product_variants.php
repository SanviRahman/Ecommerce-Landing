<?php

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::create('product_option_assignments', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_option_id')->constrained('product_options')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->primary(['product_id', 'product_option_id'], 'prod_option_assignments_pk');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('option_signature', 191);
            $table->unsignedInteger('price_override')->nullable();
            $table->unsignedInteger('old_price_override')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'option_signature'], 'variants_product_signature_unique');
            $table->index(['product_id', 'is_active', 'sort_order', 'id'], 'variants_product_active_sort_idx');
        });

        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('product_option_id')->constrained('product_options')->restrictOnDelete();
            $table->unsignedBigInteger('product_option_value_id');
            $table->timestamps();
            $table->primary(['product_variant_id', 'product_option_id'], 'variant_values_pk');
            $table->foreign(['product_option_value_id', 'product_option_id'], 'variant_value_option_fk')
                ->references(['id', 'product_option_id'])->on('product_option_values')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_assignments');
    }
};
