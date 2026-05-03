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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();

            $table->string('campaign_type')->default('single');

            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();

            $table->string('offer_text')->nullable();

            $table->json('benefits_text')->nullable();
            $table->json('comparison_text')->nullable();

            $table->unsignedInteger('old_price')->nullable();
            $table->unsignedInteger('new_price')->nullable();

            $table->string('button_text')->default('অর্ডার করুন');

            $table->string('order_form_title')->nullable();
            $table->string('order_form_subtitle')->nullable();

            $table->boolean('enable_bulk_order')->default(false);

            $table->boolean('status')->default(true);

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['slug', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
