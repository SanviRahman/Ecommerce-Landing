<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('route_type', 20)
                ->default('standard')
                ->after('slug');

            $table->string('custom_route', 190)
                ->nullable()
                ->after('route_type');

            $table->index('route_type', 'campaigns_route_type_index');
            $table->unique('custom_route', 'campaigns_custom_route_unique');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique('campaigns_custom_route_unique');
            $table->dropIndex('campaigns_route_type_index');
            $table->dropColumn(['custom_route', 'route_type']);
        });
    }
};
