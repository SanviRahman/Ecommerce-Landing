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
        if (Schema::hasTable('storefront_faqs')) {
            throw new RuntimeException('Storefront FAQs table already exists; inspect it before migration.');
        }
        // Independent of campaign-owned faqs and their campaign_id associations.
        Schema::create('storefront_faqs', static function (Blueprint $table): void {
            $table->id();
            $table->string('question', 300);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'sort_order', 'id'], 'storefront_faq_public_order_idx');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Storefront FAQs rollback requires reviewed data preservation.');
    }
};
