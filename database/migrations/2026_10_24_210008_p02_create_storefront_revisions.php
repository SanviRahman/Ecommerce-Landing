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

        Schema::create('storefront_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_page_id')->constrained('storefront_pages')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->foreignId('base_revision_id')->nullable()->constrained('storefront_revisions')->restrictOnDelete();
            $table->json('settings');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['storefront_page_id', 'revision_number'], 'storefront_page_revision_unique');
        });

        Schema::table('storefront_pages', function (Blueprint $table) {
            $table->foreign('draft_revision_id', 'storefront_draft_revision_fk')
                ->references('id')->on('storefront_revisions')->restrictOnDelete();
            $table->foreign('published_revision_id', 'storefront_published_revision_fk')
                ->references('id')->on('storefront_revisions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        P02MigrationSafety::assertIsolatedTestDatabase();

        Schema::table('storefront_pages', function (Blueprint $table) {
            $table->dropForeign('storefront_draft_revision_fk');
            $table->dropForeign('storefront_published_revision_fk');
        });
        Schema::dropIfExists('storefront_revisions');
    }
};
