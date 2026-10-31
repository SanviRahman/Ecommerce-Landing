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

        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('owner_hash', 64)->unique();
            $table->char('currency', 3)->default('BDT');
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index('expires_at');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('quoted_unit_price', 14, 2);
            $table->timestamps();
            $table->unique(['cart_id', 'product_variant_id'], 'cart_variant_unique');
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
