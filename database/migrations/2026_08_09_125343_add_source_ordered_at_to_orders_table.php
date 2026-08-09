<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'source_ordered_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('source_ordered_at')
                    ->nullable()
                    ->after('api_received_at')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'source_ordered_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['source_ordered_at']);
                $table->dropColumn('source_ordered_at');
            });
        }
    }
};
