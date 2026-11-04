<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_pixels', function (Blueprint $table): void {
            $table->boolean('capi_enabled')->default(false)->after('script_code');
            // Laravel's encrypted model cast requires TEXT (ciphertext is longer than a token).
            $table->text('capi_access_token')->nullable()->after('capi_enabled');
            $table->string('capi_test_event_code', 100)->nullable()->after('capi_access_token');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_pixels', function (Blueprint $table): void {
            $table->dropColumn(['capi_enabled', 'capi_access_token', 'capi_test_event_code']);
        });
    }
};
