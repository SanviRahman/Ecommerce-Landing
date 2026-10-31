<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\P02MigrationSafety;

return new class extends Migration {
    public function up(): void
    {
        // Explicit reviewed local forward-migration permission, never production or arbitrary MySQL.
        P02MigrationSafety::assertApprovedP02Up();
        Schema::create('storefront_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->string('phone', 11)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('storefront_account_id')->nullable()->after('customer_id')
                ->constrained('storefront_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Destructive rollback is not an automatic recovery strategy for existing orders.
        // A documented, approved backup/restore is required before account schema removal.
        throw new RuntimeException('Storefront account schema rollback requires reviewed data-preservation plan.');
    }
};
