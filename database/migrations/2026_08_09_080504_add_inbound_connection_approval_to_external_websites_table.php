<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_websites', function (Blueprint $table) {
            $table->string('inbound_approval_status', 30)
                ->default('awaiting_request')
                ->after('last_auth_failed_at');
            $table->timestamp('inbound_request_received_at')
                ->nullable()
                ->after('inbound_approval_status');
            $table->string('inbound_request_ip', 45)
                ->nullable()
                ->after('inbound_request_received_at');
            $table->json('inbound_request_meta')
                ->nullable()
                ->after('inbound_request_ip');
            $table->timestamp('inbound_approved_at')
                ->nullable()
                ->after('inbound_request_meta');
            $table->timestamp('inbound_rejected_at')
                ->nullable()
                ->after('inbound_approved_at');

            $table->index(
                ['status', 'receive_orders', 'inbound_approval_status'],
                'external_websites_inbound_approval_index'
            );
        });

        DB::table('external_websites')
            ->whereNull('deleted_at')
            ->whereNotNull('last_authenticated_at')
            ->update([
                'inbound_approval_status' => 'approved',
                'inbound_approved_at' => DB::raw('last_authenticated_at'),
            ]);

        DB::table('external_websites')
            ->whereNull('deleted_at')
            ->whereNull('last_authenticated_at')
            ->update([
                'inbound_approval_status' => 'awaiting_request',
            ]);
    }

    public function down(): void
    {
        Schema::table('external_websites', function (Blueprint $table) {
            $table->dropIndex('external_websites_inbound_approval_index');
            $table->dropColumn([
                'inbound_approval_status',
                'inbound_request_received_at',
                'inbound_request_ip',
                'inbound_request_meta',
                'inbound_approved_at',
                'inbound_rejected_at',
            ]);
        });
    }
};
