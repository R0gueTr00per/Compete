<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->string('scoring_method')->nullable()->after('target_score');
            $table->tinyInteger('judge_count')->nullable()->after('scoring_method');
            $table->string('division_filter')->nullable()->after('judge_count');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn(['scoring_method', 'judge_count', 'division_filter']);
        });
    }
};
