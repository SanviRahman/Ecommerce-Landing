<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create Laravel's database session table when it is not already present.
     * Some production hosts may have provisioned it manually; in that case,
     * validate the expected schema and let Laravel record this migration.
     */
    public function up(): void
    {
        $requiredColumns = [
            'id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity',
        ];

        if (Schema::hasTable('sessions')) {
            if (! Schema::hasColumns('sessions', $requiredColumns)) {
                throw new RuntimeException(
                    'The existing sessions table is incomplete. Back it up and repair its schema before continuing.'
                );
            }

            return;
        }

        Schema::create('sessions', function (Blueprint $table): void {
            // Session IDs are shorter than 191 characters; this length also
            // keeps the primary key compatible with older MySQL index limits.
            $table->string('id', 191)->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        // Intentionally preserve this ephemeral but shared production table.
        // It may have existed before this migration was introduced; dropping
        // it during rollback would log out every customer and administrator.
    }
};
