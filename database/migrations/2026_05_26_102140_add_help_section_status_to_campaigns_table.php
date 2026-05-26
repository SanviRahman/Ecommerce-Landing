<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaigns', 'help_section_status')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->boolean('help_section_status')
                    ->default(true)
                    ->after('faq_section_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaigns', 'help_section_status')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn('help_section_status');
            });
        }
    }
};
