<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_webhook_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('courier_account_id')
                ->nullable()
                ->constrained('courier_accounts')
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Courier Provider Information
            |--------------------------------------------------------------------------
            |
            | Example providers:
            | steadfast, pathao, paperfly, redx
            |
            */

            $table->string('provider', 50)->index();

            /*
            |--------------------------------------------------------------------------
            | Webhook Source
            |--------------------------------------------------------------------------
            |
            | webhook  = Courier webhook callback
            | api_sync = Scheduled/API status sync
            | manual   = Manual status operation
            |
            */

            $table->string('source', 30)
                ->default('webhook')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Event Identification
            |--------------------------------------------------------------------------
            */

            $table->string('event_key', 64);

            $table->string('event_name', 100)
                ->nullable()
                ->index();

            $table->string('external_event_id')
                ->nullable()
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Courier and Order References
            |--------------------------------------------------------------------------
            */

            $table->string('consignment_id')
                ->nullable()
                ->index();

            $table->string('tracking_code')
                ->nullable()
                ->index();

            $table->string('invoice')
                ->nullable()
                ->index();

            $table->string('merchant_order_id')
                ->nullable()
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Courier Status
            |--------------------------------------------------------------------------
            |
            | courier_status = Original courier status
            | mapped_status  = Local project status
            |
            */

            $table->string('courier_status', 100)
                ->nullable()
                ->index();

            $table->string('mapped_status', 50)
                ->nullable()
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Security Verification
            |--------------------------------------------------------------------------
            |
            | true  = Signature/token verified
            | false = Verification failed
            | null  = Provider does not use signature verification
            |
            */

            $table->boolean('signature_valid')
                ->nullable()
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Processing Result
            |--------------------------------------------------------------------------
            |
            | received, processing, processed, ignored, duplicate, failed
            |
            */

            $table->string('result', 30)
                ->default('received')
                ->index();

            $table->unsignedInteger('attempts')
                ->default(1);

            /*
            |--------------------------------------------------------------------------
            | Request Data
            |--------------------------------------------------------------------------
            */

            $table->json('headers')->nullable();
            $table->json('payload')->nullable();

            $table->text('error_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Processing Times
            |--------------------------------------------------------------------------
            */

            $table->timestamp('received_at')
                ->nullable();

            $table->timestamp('processed_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Duplicate Webhook Protection
            |--------------------------------------------------------------------------
            */

            $table->unique(
                [
                    'provider',
                    'courier_account_id',
                    'event_key',
                ],
                'courier_webhook_provider_account_event_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Query Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['provider', 'courier_status'],
                'courier_webhook_provider_status_index'
            );

            $table->index(
                ['provider', 'result'],
                'courier_webhook_provider_result_index'
            );

            $table->index(
                ['courier_account_id', 'created_at'],
                'courier_webhook_account_created_index'
            );

            $table->index(
                ['order_id', 'created_at'],
                'courier_webhook_order_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_webhook_logs');
    }
};