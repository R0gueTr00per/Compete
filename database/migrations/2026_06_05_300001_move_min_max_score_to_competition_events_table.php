<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->decimal('min_score', 8, 3)->nullable()->after('default_score');
            $table->decimal('max_score', 8, 3)->nullable()->after('min_score');
        });

        Schema::table('score_categories', function (Blueprint $table) {
            $table->dropColumn(['min_score', 'max_score']);
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn(['min_score', 'max_score']);
        });

        Schema::table('score_categories', function (Blueprint $table) {
            $table->decimal('min_score', 8, 3)->nullable();
            $table->decimal('max_score', 8, 3)->nullable();
        });
    }
};
