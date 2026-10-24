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

        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('input_type', 20)->default('select');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained('product_options')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('label', 100);
            $table->char('swatch_hex', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_option_id', 'code'], 'option_values_code_unique');
            // Required by the composite FK on product_variant_values.
            $table->unique(['id', 'product_option_id'], 'option_values_id_option_unique');
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
    }
};
