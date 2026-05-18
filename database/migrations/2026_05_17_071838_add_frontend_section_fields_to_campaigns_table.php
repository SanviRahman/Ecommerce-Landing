<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'hero_section_status')) {
                $table->boolean('hero_section_status')->default(true)->after('enable_bulk_order');
            }

            if (! Schema::hasColumn('campaigns', 'benefits_section_status')) {
                $table->boolean('benefits_section_status')->default(true)->after('hero_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'category_section_status')) {
                $table->boolean('category_section_status')->default(true)->after('benefits_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'product_section_status')) {
                $table->boolean('product_section_status')->default(true)->after('category_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'comparison_section_status')) {
                $table->boolean('comparison_section_status')->default(true)->after('product_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'service_section_status')) {
                $table->boolean('service_section_status')->default(true)->after('comparison_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'review_section_status')) {
                $table->boolean('review_section_status')->default(true)->after('service_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'gallery_section_status')) {
                $table->boolean('gallery_section_status')->default(true)->after('review_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'faq_section_status')) {
                $table->boolean('faq_section_status')->default(true)->after('gallery_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'order_section_status')) {
                $table->boolean('order_section_status')->default(true)->after('faq_section_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $columns = [
                'hero_section_status',
                'benefits_section_status',
                'category_section_status',
                'product_section_status',
                'comparison_section_status',
                'service_section_status',
                'review_section_status',
                'gallery_section_status',
                'faq_section_status',
                'order_section_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};