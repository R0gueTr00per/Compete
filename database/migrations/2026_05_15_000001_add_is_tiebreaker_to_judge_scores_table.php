<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judge_scores', function (Blueprint $table) {
            $table->boolean('is_tiebreaker')->default(false)->after('judge_number');
            // Add new index first so the FK on result_id always has a covering index (required by MySQL)
            $table->unique(['result_id', 'judge_number', 'is_tiebreaker']);
        });

        Schema::table('judge_scores', function (Blueprint $table) {
            $table->dropUnique(['result_id', 'judge_number']);
        });
    }

    public function down(): void
    {
        Schema::table('judge_scores', function (Blueprint $table) {
            $table->unique(['result_id', 'judge_number']);
        });

        Schema::table('judge_scores', function (Blueprint $table) {
            $table->dropUnique(['result_id', 'judge_number', 'is_tiebreaker']);
            $table->dropColumn('is_tiebreaker');
        });
    }
};
