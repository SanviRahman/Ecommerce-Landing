<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Pathao access_token is a long JWT token.
        | string/varchar(255) is not enough, so token must be LONGTEXT.
        |--------------------------------------------------------------------------
        */
        DB::statement('ALTER TABLE courier_accounts MODIFY token LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE courier_accounts MODIFY token VARCHAR(255) NULL');
    }
};