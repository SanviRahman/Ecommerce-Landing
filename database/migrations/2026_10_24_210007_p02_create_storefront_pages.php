<?php

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        P02MigrationSafety::assertApprovedP02Up();

        Schema::create('storefront_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('mode', 20)->default('campaign');
            // FK constraints are added after storefront_revisions exists (M08).
            $table->unsignedBigInteger('draft_revision_id')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::dropIfExists('storefront_pages');
    }
};
