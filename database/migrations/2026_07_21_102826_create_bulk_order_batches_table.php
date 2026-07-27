<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bulk_order_batches')) {
            return;
        }

        Schema::create('bulk_order_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_uid')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_via', 30)->index();
            $table->longText('raw_input');
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('total_customers')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_order_batches');
    }
};
