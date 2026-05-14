<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('judge_scores', 'is_tiebreaker')) {
            Schema::table('judge_scores', function (Blueprint $table) {
                $table->boolean('is_tiebreaker')->default(false)->after('judge_number');
            });
        }

        $indexes = collect(Schema::getIndexes('judge_scores'))->pluck('name');

        // Add new unique first so result_id FK always has a covering index (required by MySQL)
        if (! $indexes->contains('judge_scores_result_id_judge_number_is_tiebreaker_unique')) {
            Schema::table('judge_scores', function (Blueprint $table) {
                $table->unique(['result_id', 'judge_number', 'is_tiebreaker']);
            });
        }

        if ($indexes->contains('judge_scores_result_id_judge_number_unique')) {
            Schema::table('judge_scores', function (Blueprint $table) {
                $table->dropUnique(['result_id', 'judge_number']);
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('judge_scores'))->pluck('name');

        if (! $indexes->contains('judge_scores_result_id_judge_number_unique')) {
            Schema::table('judge_scores', function (Blueprint $table) {
                $table->unique(['result_id', 'judge_number']);
            });
        }

        if ($indexes->contains('judge_scores_result_id_judge_number_is_tiebreaker_unique')) {
            Schema::table('judge_scores', function (Blueprint $table) {
                $table->dropUnique(['result_id', 'judge_number', 'is_tiebreaker']);
            });
        }

        if (Schema::hasColumn('judge_scores', 'is_tiebreaker')) {
            Schema::table('judge_scores', function (Blueprint $table) {
                $table->dropColumn('is_tiebreaker');
            });
        }
    }
};
