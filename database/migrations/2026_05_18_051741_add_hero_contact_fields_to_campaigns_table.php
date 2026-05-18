<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'hero_whatsapp')) {
                $table->string('hero_whatsapp')->nullable()->after('button_text');
            }

            if (! Schema::hasColumn('campaigns', 'hero_phone')) {
                $table->string('hero_phone')->nullable()->after('hero_whatsapp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'hero_phone')) {
                $table->dropColumn('hero_phone');
            }

            if (Schema::hasColumn('campaigns', 'hero_whatsapp')) {
                $table->dropColumn('hero_whatsapp');
            }
        });
    }
};