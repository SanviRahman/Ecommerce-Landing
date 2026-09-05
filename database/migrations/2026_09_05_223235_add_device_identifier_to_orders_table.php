<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'device_identifier')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('device_identifier', 100)
                    ->nullable()
                    ->after('source_ip')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'device_identifier')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('device_identifier');
            });
        }
    }
};
