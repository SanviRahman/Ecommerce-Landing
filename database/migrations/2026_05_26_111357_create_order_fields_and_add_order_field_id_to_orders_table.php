<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fields')) {
            Schema::create('order_fields', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('color', 20)->default('#2563eb');
                $table->boolean('status')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['status', 'sort_order']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'order_field_id')) {
                $table->foreignId('order_field_id')
                    ->nullable()
                    ->after('assigned_employee_id')
                    ->constrained('order_fields')
                    ->nullOnDelete();
            }
        });

        // Optional one-time cleanup for old customer orders that were created as pending.
        // New customer orders will be processing from CampaignOrderController.
        DB::table('orders')
            ->where('order_status', 'pending')
            ->update(['order_status' => 'processing']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'order_field_id')) {
                $table->dropConstrainedForeignId('order_field_id');
            }
        });

        Schema::dropIfExists('order_fields');
    }
};
