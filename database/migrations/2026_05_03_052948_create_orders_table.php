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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_id')->unique();

            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('campaigns')
                ->nullOnDelete();

            $table->string('customer_name');
            $table->string('phone')->index();
            $table->text('address');

            $table->string('delivery_area')->nullable();

            $table->unsignedInteger('sub_total')->default(0);
            $table->unsignedInteger('shipping_charge')->default(0);
            $table->unsignedInteger('cod_charge')->default(0);
            $table->unsignedInteger('total_amount')->default(0);

            $table->string('payment_method')->default('cash_on_delivery');
            $table->string('payment_status')->default('cod_pending');

            $table->string('order_status')->default('pending');

            $table->boolean('is_fake')->default(false);
            $table->text('admin_note')->nullable();
            $table->text('customer_note')->nullable();

            $table->string('source_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('source_url')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('marked_fake_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['campaign_id', 'order_status']);
            $table->index(['phone', 'order_status']);
            $table->index('is_fake');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
