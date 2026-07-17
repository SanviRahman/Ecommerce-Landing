<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'created_via')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('created_via', 30)
                    ->default('frontend')
                    ->after('assigned_employee_id')
                    ->index();
            });
        }

        if (! Schema::hasColumn('orders', 'created_by_admin_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('created_by_admin_id')
                    ->nullable()
                    ->after('created_via')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('custom_order_list_moved_at')
                    ->nullable()
                    ->after('custom_order_list')
                    ->index();
            });
        }

        if (! Schema::hasColumn('orders', 'shipped_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('shipped_at')
                    ->nullable()
                    ->after('confirmed_at')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'created_by_admin_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by_admin_id');
            });
        }

        if (Schema::hasColumn('orders', 'created_via')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['created_via']);
                $table->dropColumn('created_via');
            });
        }

        if (Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['custom_order_list_moved_at']);
                $table->dropColumn('custom_order_list_moved_at');
            });
        }

        if (Schema::hasColumn('orders', 'shipped_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['shipped_at']);
                $table->dropColumn('shipped_at');
            });
        }
    }
};
