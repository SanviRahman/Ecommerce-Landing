<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a dedicated movement timestamp so Report cards can count Order List 1/2
     * based on when the order was moved, not when the order was created.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
                $table->timestamp('custom_order_list_moved_at')
                    ->nullable()
                    ->after('custom_order_list')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
                $table->dropColumn('custom_order_list_moved_at');
            }
        });
    }
};
