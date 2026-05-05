<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_pixels', function (Blueprint $table) {
            if (! Schema::hasColumn('tracking_pixels', 'script_code')) {
                $table->longText('script_code')->nullable()->after('pixel_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tracking_pixels', function (Blueprint $table) {
            if (Schema::hasColumn('tracking_pixels', 'script_code')) {
                $table->dropColumn('script_code');
            }
        });
    }
};