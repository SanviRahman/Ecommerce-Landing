<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('product_code')->unique();

            $table->unsignedInteger('purchase_price')->default(0);
            $table->unsignedInteger('old_price')->nullable();
            $table->unsignedInteger('new_price')->default(0);

            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('sold_quantity')->default(0);

            $table->string('weight_size')->nullable();

            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();

            $table->boolean('is_top_sale')->default(false);
            $table->boolean('is_feature')->default(false);
            $table->boolean('is_flash_sale')->default(false);

            $table->boolean('status')->default(true);

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->index(['is_top_sale', 'status']);
            $table->index(['is_feature', 'status']);
            $table->index(['is_flash_sale', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
