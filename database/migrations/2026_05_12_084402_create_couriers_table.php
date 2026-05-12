<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('couriers')) {
            Schema::create('couriers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique()->index();
                $table->boolean('status')->default(true)->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'courier_id')) {
                $table->foreignId('courier_id')
                    ->nullable()
                    ->after('courier_account_id')
                    ->constrained('couriers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'courier_id')) {
                $table->dropForeign(['courier_id']);
                $table->dropColumn('courier_id');
            }
        });

        Schema::dropIfExists('couriers');
    }
};