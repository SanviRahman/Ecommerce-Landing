<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('footer_youtube_url', 255)->nullable();
            $table->string('footer_facebook_url', 255)->nullable();
            $table->string('footer_instagram_url', 255)->nullable();
            $table->string('footer_linkedin_url', 255)->nullable();
            // Old SocialMedia entries remain visible until Site Settings is saved.
            $table->boolean('footer_social_managed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'footer_youtube_url', 'footer_facebook_url', 'footer_instagram_url',
                'footer_linkedin_url', 'footer_social_managed',
            ]);
        });
    }
};
