<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'hero_video_autoplay')) {
                $table->boolean('hero_video_autoplay')->default(false)->after('hero_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'hero_video_muted')) {
                $table->boolean('hero_video_muted')->default(false)->after('hero_video_autoplay');
            }

            if (! Schema::hasColumn('campaigns', 'footer_section_status')) {
                $table->boolean('footer_section_status')->default(true)->after('order_tracking_section_status');
            }

            if (! Schema::hasColumn('campaigns', 'social_media_section_status')) {
                $table->boolean('social_media_section_status')->default(true)->after('footer_section_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            foreach ([
                'social_media_section_status',
                'footer_section_status',
                'hero_video_muted',
                'hero_video_autoplay',
            ] as $column) {
                if (Schema::hasColumn('campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
