<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // organisations.slug — queried on every request in ResolveTenant middleware
        $this->addIndexIfMissing('organisations', ['slug'], 'organisations_slug_index');

        // divisions(status, location_label) — Dashboard division filtering / scheduling counts
        $this->addIndexIfMissing('divisions', ['status', 'location_label'], 'divisions_status_location_label_index');

        // enrolments(competition_id, status) — heavy result/tally queries
        $this->addIndexIfMissing('enrolments', ['competition_id', 'status'], 'enrolments_competition_id_status_index');

        // enrolment_events(division_id, removed) — scoring panel competitorRows()
        $this->addIndexIfMissing('enrolment_events', ['division_id', 'removed'], 'enrolment_events_division_id_removed_index');

        // results(division_id, placement) — medal tally sorting
        $this->addIndexIfMissing('results', ['division_id', 'placement'], 'results_division_id_placement_index');

        // judge_scores(result_id, judge_number) — score lookups in scoring panel
        $this->addIndexIfMissing('judge_scores', ['result_id', 'judge_number'], 'judge_scores_result_id_judge_number_index');
    }

    public function down(): void
    {
        $drops = [
            'organisations'    => 'organisations_slug_index',
            'divisions'        => 'divisions_status_location_label_index',
            'enrolments'       => 'enrolments_competition_id_status_index',
            'enrolment_events' => 'enrolment_events_division_id_removed_index',
            'results'          => 'results_division_id_placement_index',
            'judge_scores'     => 'judge_scores_result_id_judge_number_index',
        ];

        foreach ($drops as $table => $index) {
            $existing = collect(Schema::getIndexes($table))->pluck('name');
            if ($existing->contains($index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
            }
        }
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        $existing = collect(Schema::getIndexes($table))->pluck('name');
        if ($existing->contains($name)) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
    }
};
