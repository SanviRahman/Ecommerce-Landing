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
        Schema::create('bulk_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('campaigns')
                ->nullOnDelete();

            $table->string('customer_name');
            $table->string('phone')->index();
            $table->string('company_name')->nullable();

            $table->string('product_name')->nullable();
            $table->unsignedInteger('expected_quantity')->default(1);

            $table->text('address')->nullable();
            $table->text('requirement_message')->nullable();

            $table->string('status')->default('new');

            $table->text('admin_note')->nullable();

            $table->string('source_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('source_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['phone', 'status']);
            $table->index('campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_orders');
    }
};
