<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'is_free_delivery')) {
                $table->boolean('is_free_delivery')
                    ->default(false)
                    ->after('is_flash_sale')
                    ->index();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'courier_account_id')) {
                $table->foreignId('courier_account_id')
                    ->nullable()
                    ->after('courier_service')
                    ->constrained('courier_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'is_free_delivery')) {
                $table->boolean('is_free_delivery')
                    ->default(false)
                    ->after('shipping_charge')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'courier_account_id')) {
                $table->dropForeign(['courier_account_id']);
                $table->dropColumn('courier_account_id');
            }

            if (Schema::hasColumn('orders', 'is_free_delivery')) {
                $table->dropColumn('is_free_delivery');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_free_delivery')) {
                $table->dropColumn('is_free_delivery');
            }
        });
    }
};