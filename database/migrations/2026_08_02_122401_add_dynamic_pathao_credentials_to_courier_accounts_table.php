<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('courier_accounts', 'auth_username')) {
                $table->string('auth_username')->nullable()->after('secret_key');
            }

            if (! Schema::hasColumn('courier_accounts', 'auth_password')) {
                $table->text('auth_password')->nullable()->after('auth_username');
            }

            if (! Schema::hasColumn('courier_accounts', 'refresh_token')) {
                $table->longText('refresh_token')->nullable()->after('token');
            }

            if (! Schema::hasColumn('courier_accounts', 'token_type')) {
                $table->string('token_type', 50)->nullable()->after('refresh_token');
            }

            if (! Schema::hasColumn('courier_accounts', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->index()->after('token_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courier_accounts', function (Blueprint $table) {
            foreach ([
                'token_expires_at',
                'token_type',
                'refresh_token',
                'auth_password',
                'auth_username',
            ] as $column) {
                if (Schema::hasColumn('courier_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
