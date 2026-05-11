<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('name')->default('No Courier');

            // no_courier, steadfast, pathao etc.
            $table->string('code')->default('no_courier')->index();

            $table->string('base_url')->nullable();
            $table->string('api_key')->nullable();
            $table->string('secret_key')->nullable();
            $table->string('token')->nullable();

            $table->boolean('is_default')->default(false)->index();
            $table->boolean('status')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_accounts');
    }
};
