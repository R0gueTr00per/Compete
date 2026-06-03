<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // match_penalties: both FK columns missing indexes
        $this->addIndexIfMissing('match_penalties', ['result_id'], 'match_penalties_result_id_index');
        $this->addIndexIfMissing('match_penalties', ['round_robin_match_id'], 'match_penalties_round_robin_match_id_index');

        // enrolments: cart_id and rank_id FKs missing indexes
        $this->addIndexIfMissing('enrolments', ['cart_id'], 'enrolments_cart_id_index');
        $this->addIndexIfMissing('enrolments', ['rank_id'], 'enrolments_rank_id_index');

        // results: enrolment_event_id FK missing index (only division_id was previously indexed)
        $this->addIndexIfMissing('results', ['enrolment_event_id'], 'results_enrolment_event_id_index');

        // competitions: organisation_id FK missing index (used in every tenant-scoped query)
        $this->addIndexIfMissing('competitions', ['organisation_id'], 'competitions_organisation_id_index');

        // competitor_profiles: organisation_id FK missing index
        $this->addIndexIfMissing('competitor_profiles', ['organisation_id'], 'competitor_profiles_organisation_id_index');
    }

    public function down(): void
    {
        Schema::table('match_penalties', function (Blueprint $table) {
            $table->dropIndex('match_penalties_result_id_index');
            $table->dropIndex('match_penalties_round_robin_match_id_index');
        });
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropIndex('enrolments_cart_id_index');
            $table->dropIndex('enrolments_rank_id_index');
        });
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex('results_enrolment_event_id_index');
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropIndex('competitions_organisation_id_index');
        });
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->dropIndex('competitor_profiles_organisation_id_index');
        });
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        $existing = collect(Schema::getIndexes($table))->pluck('name');
        if ($existing->contains($name)) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }
};
