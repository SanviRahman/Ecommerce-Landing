<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_websites', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->index();

            $table->text('api_token');
            $table->timestamp('token_updated_at')->nullable();

            $table->boolean('status')->default(true);
            $table->boolean('receive_orders')->default(true);
            $table->boolean('send_orders')->default(false);
            $table->boolean('auto_send_orders')->default(true);

            $table->text('remote_order_endpoint')->nullable();
            $table->text('remote_health_endpoint')->nullable();
            $table->text('remote_api_token')->nullable();

            $table->unsignedSmallInteger('request_timeout')->default(15);

            $table->text('notes')->nullable();

            $table->timestamp('last_order_received_at')->nullable();
            $table->timestamp('last_authenticated_at')->nullable();
            $table->timestamp('last_auth_failed_at')->nullable();

            $table->timestamp('last_connection_tested_at')->nullable();
            $table->string('last_connection_status', 30)->nullable();
            $table->text('last_connection_message')->nullable();

            $table->timestamp('last_order_sent_at')->nullable();
            $table->timestamp('last_send_failed_at')->nullable();
            $table->text('last_send_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['status', 'deleted_at'],
                'external_websites_status_deleted_index'
            );

            $table->index(
                ['status', 'receive_orders', 'send_orders'],
                'external_websites_direction_status_index'
            );
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('external_website_id')
                ->nullable()
                ->constrained('external_websites')
                ->nullOnDelete();

            $table->string('external_order_id', 191)->nullable();

            $table->json('external_payload')->nullable();

            $table->timestamp('api_received_at')->nullable();

            $table->uuid('sync_uuid')->nullable();

            $table->unique(
                ['external_website_id', 'external_order_id'],
                'orders_external_website_order_unique'
            );

            $table->unique(
                'sync_uuid',
                'orders_sync_uuid_unique'
            );
        });

        Schema::create('external_order_syncs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('external_website_id')
                ->constrained('external_websites')
                ->cascadeOnDelete();

            $table->uuid('sync_uuid');

            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['order_id', 'external_website_id'],
                'external_order_sync_order_website_unique'
            );

            $table->index(
                ['external_website_id', 'status'],
                'external_order_sync_website_status_index'
            );

            $table->index(
                'sync_uuid',
                'external_order_sync_uuid_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_order_syncs');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(
                'orders_external_website_order_unique'
            );

            $table->dropUnique(
                'orders_sync_uuid_unique'
            );

            $table->dropForeign([
                'external_website_id',
            ]);

            $table->dropColumn([
                'external_website_id',
                'external_order_id',
                'external_payload',
                'api_received_at',
                'sync_uuid',
            ]);
        });

        Schema::dropIfExists('external_websites');
    }
};