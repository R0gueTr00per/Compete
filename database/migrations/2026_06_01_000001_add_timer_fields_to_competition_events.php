<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->unsignedInteger('round_duration_seconds')->nullable()->after('target_score');
            $table->unsignedInteger('tiebreak_duration_seconds')->nullable()->after('round_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn(['round_duration_seconds', 'tiebreak_duration_seconds']);
        });
    }
};
