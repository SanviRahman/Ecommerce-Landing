<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $addSuccess = ! Schema::hasColumn('orders', 'fraud_check_success');
        $addCancel = ! Schema::hasColumn('orders', 'fraud_check_cancel');
        $addCheckedAt = ! Schema::hasColumn('orders', 'fraud_checked_at');

        if (! $addSuccess && ! $addCancel && ! $addCheckedAt) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use (
            $addSuccess,
            $addCancel,
            $addCheckedAt
        ) {
            if ($addSuccess) {
                $table->unsignedInteger('fraud_check_success')->nullable();
            }

            if ($addCancel) {
                $table->unsignedInteger('fraud_check_cancel')->nullable();
            }

            if ($addCheckedAt) {
                $table->timestamp('fraud_checked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};