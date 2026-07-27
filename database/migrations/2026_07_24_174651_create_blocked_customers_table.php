<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('source_order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->string('customer_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->boolean('block_phone')->default(false);
            $table->boolean('block_ip')->default(false);
            $table->text('reason')->nullable();
            $table->boolean('status')->default(true);

            $table->foreignId('blocked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('unblocked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('unblocked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'phone']);
            $table->index(['status', 'ip_address']);
            $table->index(['block_phone', 'status']);
            $table->index(['block_ip', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_customers');
    }
};
