<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('round_robin_matches', function (Blueprint $table) {
            $table->decimal('home_score', 8, 3)->nullable()->after('home_result');
            $table->decimal('away_score', 8, 3)->nullable()->after('home_score');
        });
    }

    public function down(): void
    {
        Schema::table('round_robin_matches', function (Blueprint $table) {
            $table->dropColumn(['home_score', 'away_score']);
        });
    }
};
