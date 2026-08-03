<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaigns', 'order_tracking_section_status')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->boolean('order_tracking_section_status')
                    ->default(true)
                    ->after('order_section_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaigns', 'order_tracking_section_status')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn('order_tracking_section_status');
            });
        }
    }
};
