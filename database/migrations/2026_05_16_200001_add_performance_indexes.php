<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Divisions: compound index for the common filter (competition_event_id + status)
        $this->addIndexIfMissing('divisions', ['competition_event_id', 'status'], 'divisions_competition_event_id_status_index');

        // Enrolments: explicit index on competitor_id (FK index may vary by DB)
        $this->addIndexIfMissing('enrolments', ['competitor_id'], 'enrolments_competitor_id_index');

        // EnrolmentEvents: explicit indexes for common lookup columns
        $this->addIndexIfMissing('enrolment_events', ['competition_event_id'], 'enrolment_events_competition_event_id_index');
        $this->addIndexIfMissing('enrolment_events', ['division_id'], 'enrolment_events_division_id_index');

        // RoundRobinMatches: explicit indexes on home/away FK columns
        $this->addIndexIfMissing('round_robin_matches', ['home_enrolment_event_id'], 'round_robin_matches_home_enrolment_event_id_index');
        $this->addIndexIfMissing('round_robin_matches', ['away_enrolment_event_id'], 'round_robin_matches_away_enrolment_event_id_index');
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropIndex('divisions_competition_event_id_status_index');
        });
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropIndex('enrolments_competitor_id_index');
        });
        Schema::table('enrolment_events', function (Blueprint $table) {
            $table->dropIndex('enrolment_events_competition_event_id_index');
            $table->dropIndex('enrolment_events_division_id_index');
        });
        Schema::table('round_robin_matches', function (Blueprint $table) {
            $table->dropIndex('round_robin_matches_home_enrolment_event_id_index');
            $table->dropIndex('round_robin_matches_away_enrolment_event_id_index');
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
