<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('fraud_check_success')->nullable()->after('source_url');
            $table->unsignedInteger('fraud_check_cancel')->nullable()->after('fraud_check_success');
            $table->timestamp('fraud_checked_at')->nullable()->after('fraud_check_cancel');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'fraud_check_success',
                'fraud_check_cancel',
                'fraud_checked_at',
            ]);
        });
    }
};
