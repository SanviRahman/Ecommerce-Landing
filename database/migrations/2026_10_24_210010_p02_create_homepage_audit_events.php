<?php

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::create('homepage_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_page_id')->constrained('storefront_pages')->restrictOnDelete();
            $table->foreignId('storefront_revision_id')->nullable()->constrained('storefront_revisions')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30);
            $table->unsignedBigInteger('from_version');
            $table->unsignedBigInteger('to_version');
            $table->json('summary');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['storefront_page_id', 'id'], 'homepage_audit_page_idx');
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::dropIfExists('homepage_audit_events');
    }
};
