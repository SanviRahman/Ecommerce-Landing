<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courier_webhook_logs')) {
            return;
        }

        $missing = [
            'source' => ! Schema::hasColumn('courier_webhook_logs', 'source'),
            'event_name' => ! Schema::hasColumn('courier_webhook_logs', 'event_name'),
            'external_event_id' => ! Schema::hasColumn('courier_webhook_logs', 'external_event_id'),
            'tracking_code' => ! Schema::hasColumn('courier_webhook_logs', 'tracking_code'),
            'merchant_order_id' => ! Schema::hasColumn('courier_webhook_logs', 'merchant_order_id'),
            'mapped_status' => ! Schema::hasColumn('courier_webhook_logs', 'mapped_status'),
            'signature_valid' => ! Schema::hasColumn('courier_webhook_logs', 'signature_valid'),
            'attempts' => ! Schema::hasColumn('courier_webhook_logs', 'attempts'),
            'headers' => ! Schema::hasColumn('courier_webhook_logs', 'headers'),
        ];

        if (! in_array(true, $missing, true)) {
            return;
        }

        Schema::table('courier_webhook_logs', function (Blueprint $table) use ($missing) {
            if ($missing['source']) {
                $table->string('source', 30)->default('webhook')->index();
            }

            if ($missing['event_name']) {
                $table->string('event_name', 100)->nullable()->index();
            }

            if ($missing['external_event_id']) {
                $table->string('external_event_id')->nullable()->index();
            }

            if ($missing['tracking_code']) {
                $table->string('tracking_code')->nullable()->index();
            }

            if ($missing['merchant_order_id']) {
                $table->string('merchant_order_id')->nullable()->index();
            }

            if ($missing['mapped_status']) {
                $table->string('mapped_status', 50)->nullable()->index();
            }

            if ($missing['signature_valid']) {
                $table->boolean('signature_valid')->nullable()->index();
            }

            if ($missing['attempts']) {
                $table->unsignedInteger('attempts')->default(1);
            }

            if ($missing['headers']) {
                $table->json('headers')->nullable();
            }
        });
    }

    public function down(): void
    {
        /*
         * Intentionally empty. The columns are generic and may be used by
         * SteadFast, Pathao, Paperfly, RedX, or future courier providers.
         */
    }
};
