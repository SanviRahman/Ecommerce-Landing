<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'steadfast_consignment_id')) {
                $table->string('steadfast_consignment_id')
                    ->nullable()
                    ->after('courier_service');
            }

            if (! Schema::hasColumn('orders', 'steadfast_tracking_code')) {
                $table->string('steadfast_tracking_code')
                    ->nullable()
                    ->after('steadfast_consignment_id');
            }

            if (! Schema::hasColumn('orders', 'steadfast_status')) {
                $table->string('steadfast_status')
                    ->nullable()
                    ->after('steadfast_tracking_code');
            }

            if (! Schema::hasColumn('orders', 'steadfast_note')) {
                $table->text('steadfast_note')
                    ->nullable()
                    ->after('steadfast_status');
            }

            if (! Schema::hasColumn('orders', 'steadfast_response')) {
                $table->json('steadfast_response')
                    ->nullable()
                    ->after('steadfast_note');
            }

            if (! Schema::hasColumn('orders', 'steadfast_sent_at')) {
                $table->timestamp('steadfast_sent_at')
                    ->nullable()
                    ->after('steadfast_response');
            }

            if (! Schema::hasColumn('orders', 'steadfast_synced_at')) {
                $table->timestamp('steadfast_synced_at')
                    ->nullable()
                    ->after('steadfast_sent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'steadfast_consignment_id',
                'steadfast_tracking_code',
                'steadfast_status',
                'steadfast_note',
                'steadfast_response',
                'steadfast_sent_at',
                'steadfast_synced_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};