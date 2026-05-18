<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'embed_video_url')) {
                $table->text('embed_video_url')->nullable()->after('offer_text');
            }

            if (! Schema::hasColumn('campaigns', 'section_titles')) {
                $table->json('section_titles')->nullable()->after('comparison_text');
            }

            if (! Schema::hasColumn('campaigns', 'service_items')) {
                $table->json('service_items')->nullable()->after('section_titles');
            }

            if (! Schema::hasColumn('campaigns', 'help_content')) {
                $table->json('help_content')->nullable()->after('service_items');
            }
        });

        if (! Schema::hasTable('campaign_category')) {
            Schema::create('campaign_category', function (Blueprint $table) {
                $table->foreignId('campaign_id')
                    ->constrained('campaigns')
                    ->cascadeOnDelete();

                $table->foreignId('category_id')
                    ->constrained('categories')
                    ->cascadeOnDelete();

                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->primary(['campaign_id', 'category_id']);
            });
        }

        if (! Schema::hasTable('campaign_brand')) {
            Schema::create('campaign_brand', function (Blueprint $table) {
                $table->foreignId('campaign_id')
                    ->constrained('campaigns')
                    ->cascadeOnDelete();

                $table->foreignId('brand_id')
                    ->constrained('brands')
                    ->cascadeOnDelete();

                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->primary(['campaign_id', 'brand_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_brand');
        Schema::dropIfExists('campaign_category');

        Schema::table('campaigns', function (Blueprint $table) {
            foreach (['embed_video_url', 'section_titles', 'service_items', 'help_content'] as $column) {
                if (Schema::hasColumn('campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};