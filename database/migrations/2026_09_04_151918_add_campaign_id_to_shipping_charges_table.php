<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shipping_charges', 'campaign_id')) {
            Schema::table('shipping_charges', function (Blueprint $table) {
                $table->foreignId('campaign_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('campaigns')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipping_charges', 'campaign_id')) {
            Schema::table('shipping_charges', function (Blueprint $table) {
                $table->dropConstrainedForeignId('campaign_id');
            });
        }
    }
};