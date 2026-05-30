<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'invoice_printed_at')) {
                $table->timestamp('invoice_printed_at')->nullable()->after('customer_note');
            }

            if (! Schema::hasColumn('orders', 'invoice_print_count')) {
                $table->unsignedInteger('invoice_print_count')->default(0)->after('invoice_printed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'invoice_print_count')) {
                $table->dropColumn('invoice_print_count');
            }

            if (Schema::hasColumn('orders', 'invoice_printed_at')) {
                $table->dropColumn('invoice_printed_at');
            }
        });
    }
};
