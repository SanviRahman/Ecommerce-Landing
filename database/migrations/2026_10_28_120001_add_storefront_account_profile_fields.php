<?php

declare(strict_types=1);

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Only personal/address fields are new; profile photos reuse existing Spatie media rows.
        // Forward only; the main local DB needs explicit P02 approval and identity checks.
        P02MigrationSafety::assertApprovedP02Up();
        Schema::table('storefront_accounts', static function (Blueprint $table): void {
            $table->string('gender', 24)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('division', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('upazila', 100)->nullable();
        });
    }

    public function down(): void
    {
        // Reversible only after a reviewed backup/data migration plan; protect customer profiles.
        throw new RuntimeException('Profile field rollback requires a reviewed data-preservation plan.');
    }
};
