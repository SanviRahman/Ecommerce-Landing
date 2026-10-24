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

        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_revision_id')->constrained('storefront_revisions')->restrictOnDelete();
            $table->uuid('section_key');
            $table->string('type', 40);
            $table->string('title', 160)->nullable();
            $table->string('subtitle', 200)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('show_desktop')->default(true);
            $table->boolean('show_mobile')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source_type', 30)->default('none');
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->boolean('include_descendants')->default(false);
            $table->string('product_sort', 30)->default('manual');
            $table->unsignedInteger('product_limit')->default(8);
            $table->json('layout');
            $table->json('styles');
            $table->json('settings');
            $table->timestamps();
            $table->unique(['storefront_revision_id', 'section_key'], 'homepage_section_revision_key_unique');
            $table->index(['storefront_revision_id', 'sort_order', 'id'], 'homepage_section_sort_idx');
        });

        Schema::create('homepage_section_products', function (Blueprint $table) {
            $table->foreignId('homepage_section_id')->constrained('homepage_sections')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->primary(['homepage_section_id', 'product_id'], 'section_products_pk');
            $table->index(['homepage_section_id', 'sort_order', 'product_id'], 'section_product_sort_idx');
        });

        Schema::create('homepage_section_categories', function (Blueprint $table) {
            $table->foreignId('homepage_section_id')->constrained('homepage_sections')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->primary(['homepage_section_id', 'category_id'], 'section_categories_pk');
            $table->index(['homepage_section_id', 'sort_order', 'category_id'], 'section_category_sort_idx');
        });

        Schema::create('homepage_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained('homepage_sections')->restrictOnDelete();
            $table->uuid('item_key');
            $table->string('type', 30);
            $table->string('title', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->string('link_label', 80)->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->foreignId('review_id')->nullable()->constrained('reviews')->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->restrictOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings');
            $table->timestamps();
            $table->unique(['homepage_section_id', 'item_key'], 'homepage_item_section_key_unique');
            $table->index(['homepage_section_id', 'sort_order', 'id'], 'homepage_item_sort_idx');
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::dropIfExists('homepage_section_items');
        Schema::dropIfExists('homepage_section_categories');
        Schema::dropIfExists('homepage_section_products');
        Schema::dropIfExists('homepage_sections');
    }
};
