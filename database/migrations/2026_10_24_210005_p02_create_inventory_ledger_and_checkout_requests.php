<?php

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->uuid('event_uuid');
            $table->string('operation_key', 191)->unique();
            $table->string('kind', 30);
            $table->integer('quantity_delta');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_variant_id', 'id'], 'inventory_variant_id_idx');
            $table->index(['order_item_id', 'id'], 'inventory_item_id_idx');
        });

        Schema::create('checkout_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('owner_hash', 64);
            $table->uuid('idempotency_key');
            $table->string('channel', 20);
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->restrictOnDelete();
            $table->char('request_hash', 64);
            $table->string('status', 20)->default('processing');
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['owner_hash', 'idempotency_key'], 'checkout_owner_key_unique');
            $table->index(['status', 'created_at'], 'checkout_status_created_idx');
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::dropIfExists('checkout_requests');
        Schema::dropIfExists('inventory_movements');
    }
};
