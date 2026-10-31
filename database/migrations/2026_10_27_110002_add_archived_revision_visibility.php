<?php

declare(strict_types=1);

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        P02MigrationSafety::assertApprovedP02Up();
        if (! Schema::hasTable('storefront_revisions') || Schema::hasColumn('storefront_revisions', 'deleted_at')) {
            throw new RuntimeException('Unexpected storefront revisions schema; review before migration.');
        }
        // A reversible visibility marker; content, linked media and audit rows remain untouched.
        Schema::table('storefront_revisions', static function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Revision-visibility rollback requires review to preserve archived history.');
    }
};
