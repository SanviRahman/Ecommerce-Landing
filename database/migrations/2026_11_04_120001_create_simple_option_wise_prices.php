<?php

declare(strict_types=1);

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        P02MigrationSafety::assertApprovedP02Up();

        // MySQL DDL is not transactional. A previous failed attempt can leave
        // these two tables behind while the migration is still marked pending.
        if (! Schema::hasTable('product_option_choices')) {
            Schema::create('product_option_choices', function (Blueprint $table): void {
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('product_option_value_id')->constrained('product_option_values')->restrictOnDelete();
                $table->timestamps();
                $table->primary(['product_id', 'product_option_value_id'], 'product_choices_pk');
            });
        }

        if (! Schema::hasTable('product_piece_prices')) {
            Schema::create('product_piece_prices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('product_option_value_id')->constrained('product_option_values')->restrictOnDelete();
                $table->unsignedInteger('price');
                $table->unsignedInteger('old_price')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'product_option_value_id'], 'product_piece_price_unique');
            });
        }

        // Each cart may contain the same product with different option choices.
        if (! Schema::hasColumn('cart_items', 'selection_key')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->char('selection_key', 64)->default('default');
            });
        }
        if (! Schema::hasColumn('cart_items', 'selected_options')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->json('selected_options')->nullable();
            });
        }

        // IMPORTANT: MySQL/InnoDB may use cart_variant_unique for the cart_id
        // foreign key. Install an index with the same leading column(s) BEFORE
        // dropping the old unique, otherwise MySQL raises error 1553.
        if (! Schema::hasIndex('cart_items', 'cart_variant_selection_unique')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->unique(
                    ['cart_id', 'product_variant_id', 'selection_key'],
                    'cart_variant_selection_unique'
                );
            });
        }
        if (Schema::hasIndex('cart_items', 'cart_variant_unique')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->dropUnique('cart_variant_unique');
            });
        }
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        // Restore the old index first, so cart_id retains a supporting index.
        // Rollback requires no duplicate (cart_id, product_variant_id) rows.
        if (! Schema::hasIndex('cart_items', 'cart_variant_unique')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->unique(['cart_id', 'product_variant_id'], 'cart_variant_unique');
            });
        }
        if (Schema::hasIndex('cart_items', 'cart_variant_selection_unique')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->dropUnique('cart_variant_selection_unique');
            });
        }
        if (Schema::hasColumn('cart_items', 'selected_options')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->dropColumn('selected_options');
            });
        }
        if (Schema::hasColumn('cart_items', 'selection_key')) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->dropColumn('selection_key');
            });
        }

        Schema::dropIfExists('product_piece_prices');
        Schema::dropIfExists('product_option_choices');
    }
};
