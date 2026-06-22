<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // judge_scores(result_id, is_tiebreaker) — scoring service filters on both columns;
        // existing (result_id, judge_number) index doesn't cover is_tiebreaker lookups
        $this->addIndexIfMissing('judge_scores', ['result_id', 'is_tiebreaker'], 'judge_scores_result_id_is_tiebreaker_index');

        // match_penalties(result_id) — FK join used in every penalty log lookup
        $this->addIndexIfMissing('match_penalties', ['result_id'], 'match_penalties_result_id_index');

        // round_robin_matches(division_id, bracket, round) — bracket navigation queries
        $this->addIndexIfMissing('round_robin_matches', ['division_id', 'bracket', 'round'], 'round_robin_matches_division_id_bracket_round_index');

        // competitions(organisation_id, status) — dashboard/navigation filtering
        $this->addIndexIfMissing('competitions', ['organisation_id', 'status'], 'competitions_organisation_id_status_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('judge_scores', 'judge_scores_result_id_is_tiebreaker_index');
        $this->dropIndexIfExists('match_penalties', 'match_penalties_result_id_index');
        $this->dropIndexIfExists('round_robin_matches', 'round_robin_matches_division_id_bracket_round_index');
        $this->dropIndexIfExists('competitions', 'competitions_organisation_id_status_index');
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        $existing = collect(Schema::getIndexes($table))->pluck('name');
        if (! $existing->contains($name)) {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        $existing = collect(Schema::getIndexes($table))->pluck('name');
        if ($existing->contains($name)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        }
    }
};
