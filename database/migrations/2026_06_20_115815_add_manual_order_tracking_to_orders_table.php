<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track how an order was created so admin-manual orders can be identified
     * reliably without depending on URL, IP address, notes or UI state.
     */
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
    }
};
