<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            if (! Schema::hasColumn('couriers', 'merchant_id')) {
                $table->string('merchant_id')->nullable()->after('code');
            }

            if (! Schema::hasColumn('couriers', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('merchant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            if (Schema::hasColumn('couriers', 'phone_number')) {
                $table->dropColumn('phone_number');
            }

            if (Schema::hasColumn('couriers', 'merchant_id')) {
                $table->dropColumn('merchant_id');
            }
        });
    }
};