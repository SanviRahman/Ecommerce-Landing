<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'custom_order_list')) {
                $table->string('custom_order_list')
                    ->nullable()
                    ->index()
                    ->after('order_field_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'custom_order_list')) {
                $table->dropColumn('custom_order_list');
            }
        });
    }
};
