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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('website_name');
            $table->string('phone')->nullable();
            $table->string('hotline')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('messenger_link')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('top_headline')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('business_short_description')->nullable();
            $table->string('working_hours')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
