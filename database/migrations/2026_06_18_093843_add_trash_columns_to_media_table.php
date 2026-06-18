<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add lightweight Trash Bin columns for Spatie Media Library records.
     *
     * Spatie's Media model does not use SoftDeletes by default. Therefore we do
     * not call delete() for normal delete actions; we mark trashed_at instead.
     * Force Delete will still call Spatie delete() and remove the physical file.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (! Schema::hasColumn('media', 'trashed_at')) {
                $table->timestamp('trashed_at')->nullable()->after('updated_at')->index();
            }

            if (! Schema::hasColumn('media', 'trashed_by')) {
                $table->unsignedBigInteger('trashed_by')->nullable()->after('trashed_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (Schema::hasColumn('media', 'trashed_by')) {
                $table->dropColumn('trashed_by');
            }

            if (Schema::hasColumn('media', 'trashed_at')) {
                $table->dropColumn('trashed_at');
            }
        });
    }
};
