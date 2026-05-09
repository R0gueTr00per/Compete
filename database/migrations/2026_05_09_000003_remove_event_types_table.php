<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns to competition_events
        Schema::table('competition_events', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('competition_id');
            $table->boolean('requires_partner')->default(false)->after('division_filter');
            $table->boolean('requires_weight_check')->default(false)->after('requires_partner');
        });

        // 2. Populate from event_types
        DB::statement('
            UPDATE competition_events
            SET
                name                = (SELECT name FROM event_types WHERE event_types.id = competition_events.event_type_id),
                requires_partner    = (SELECT requires_partner FROM event_types WHERE event_types.id = competition_events.event_type_id),
                requires_weight_check = (SELECT requires_weight_check FROM event_types WHERE event_types.id = competition_events.event_type_id),
                scoring_method      = COALESCE(competition_events.scoring_method, (SELECT scoring_method FROM event_types WHERE event_types.id = competition_events.event_type_id)),
                judge_count         = COALESCE(competition_events.judge_count, (SELECT judge_count FROM event_types WHERE event_types.id = competition_events.event_type_id)),
                division_filter     = COALESCE(competition_events.division_filter, (SELECT division_filter FROM event_types WHERE event_types.id = competition_events.event_type_id)),
                tournament_format   = COALESCE(competition_events.tournament_format, (SELECT tournament_format FROM event_types WHERE event_types.id = competition_events.event_type_id))
        ');

        // 3. Make name non-nullable now it's populated
        Schema::table('competition_events', function (Blueprint $table) {
            $table->string('name', 100)->nullable(false)->change();
        });

        // 4. Drop event_type_id FK and column
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropForeign(['event_type_id']);
            $table->dropColumn('event_type_id');
        });

        // 5. Drop event_types table
        Schema::dropIfExists('event_types');
    }

    public function down(): void
    {
        // Recreate event_types (minimal — data is lost)
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('scoring_method', 30)->default('judges_total');
            $table->string('division_filter', 30)->default('age_rank_sex');
            $table->boolean('requires_partner')->default(false);
            $table->boolean('requires_weight_check')->default(false);
            $table->string('tournament_format', 30)->default('once_off');
            $table->tinyInteger('judge_count')->default(0);
            $table->tinyInteger('default_target_score')->nullable();
            $table->timestamps();
        });

        Schema::table('competition_events', function (Blueprint $table) {
            $table->foreignId('event_type_id')->nullable()->constrained('event_types');
            $table->dropColumn(['name', 'requires_partner', 'requires_weight_check']);
        });
    }
};
