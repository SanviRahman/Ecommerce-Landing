<?php

declare(strict_types=1);

use App\Support\P02MigrationSafety;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Forward-only; an existing main database can migrate only through the
        // reviewed local identity guard. No broad production release is implied.
        P02MigrationSafety::assertApprovedP02Up();
        if (Schema::hasTable('storefront_contact_messages')) {
            throw new RuntimeException('Contact messages table already exists; review schema before migrating.');
        }
        Schema::create('storefront_contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->text('name'); // encrypted Laravel cast
            $table->text('email'); // encrypted Laravel cast
            $table->text('phone')->nullable(); // encrypted Laravel cast
            $table->string('subject', 32)->default('general');
            $table->longText('message'); // encrypted Laravel cast
            $table->string('status', 16)->default('new')->index();
            $table->text('admin_note')->nullable(); // encrypted Laravel cast
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Contact messages rollback must be reviewed to preserve customer messages.');
    }
};
