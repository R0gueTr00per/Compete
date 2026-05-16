<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('results', ['division_id'], 'results_division_id_index');
        $this->addIndexIfMissing('judge_scores', ['result_id'], 'judge_scores_result_id_index');
        $this->addIndexIfMissing('enrolment_events', ['enrolment_id'], 'enrolment_events_enrolment_id_index');
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex('results_division_id_index');
        });
        Schema::table('judge_scores', function (Blueprint $table) {
            $table->dropIndex('judge_scores_result_id_index');
        });
        Schema::table('enrolment_events', function (Blueprint $table) {
            $table->dropIndex('enrolment_events_enrolment_id_index');
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
