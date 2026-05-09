<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change from enum to string so round_robin (and any future formats) can be added
        // without a destructive table rebuild on SQLite or an enum ALTER on MySQL.
        Schema::table('event_types', function (Blueprint $table) {
            $table->string('tournament_format', 30)->default('once_off')->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->enum('tournament_format', ['once_off', 'single_elimination', 'double_elimination'])
                ->default('once_off')->change();
        });
    }
};
