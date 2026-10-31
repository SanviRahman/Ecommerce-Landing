<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_purge_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('original_order_id')->unique();
            $table->string('invoice_id', 191)->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedTinyInteger('inventory_version')->default(0);
            $table->json('audit_snapshot');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // The archive must remain intact after any real deletion. Do not allow
        // a rollback to silently erase immutable financial/inventory evidence.
        if (Schema::hasTable('order_purge_audits')
            && \Illuminate\Support\Facades\DB::table('order_purge_audits')->exists()) {
            throw new \RuntimeException('Cannot roll back order_purge_audits containing historical purge evidence.');
        }
        Schema::dropIfExists('order_purge_audits');
    }
};
