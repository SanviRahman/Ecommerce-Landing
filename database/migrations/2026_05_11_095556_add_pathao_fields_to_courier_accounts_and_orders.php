<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('courier_accounts', 'settings')) {
                $table->json('settings')->nullable()->after('token');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'pathao_consignment_id')) {
                $table->string('pathao_consignment_id')->nullable()->index()->after('steadfast_synced_at');
            }

            if (! Schema::hasColumn('orders', 'pathao_merchant_order_id')) {
                $table->string('pathao_merchant_order_id')->nullable()->index()->after('pathao_consignment_id');
            }

            if (! Schema::hasColumn('orders', 'pathao_status')) {
                $table->string('pathao_status')->nullable()->after('pathao_merchant_order_id');
            }

            if (! Schema::hasColumn('orders', 'pathao_delivery_fee')) {
                $table->decimal('pathao_delivery_fee', 10, 2)->default(0)->after('pathao_status');
            }

            if (! Schema::hasColumn('orders', 'pathao_note')) {
                $table->text('pathao_note')->nullable()->after('pathao_delivery_fee');
            }

            if (! Schema::hasColumn('orders', 'pathao_response')) {
                $table->json('pathao_response')->nullable()->after('pathao_note');
            }

            if (! Schema::hasColumn('orders', 'pathao_sent_at')) {
                $table->timestamp('pathao_sent_at')->nullable()->after('pathao_response');
            }

            if (! Schema::hasColumn('orders', 'pathao_synced_at')) {
                $table->timestamp('pathao_synced_at')->nullable()->after('pathao_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'pathao_consignment_id',
                'pathao_merchant_order_id',
                'pathao_status',
                'pathao_delivery_fee',
                'pathao_note',
                'pathao_response',
                'pathao_sent_at',
                'pathao_synced_at',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('courier_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('courier_accounts', 'settings')) {
                $table->dropColumn('settings');
            }
        });
    }
};