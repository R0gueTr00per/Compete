<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add plain index first so the division_id FK has a backing index after the unique is dropped
        Schema::table('round_robin_matches', function (Blueprint $table) {
            $table->index('division_id', 'rrm_division_idx');
        });

        Schema::table('round_robin_matches', function (Blueprint $table) {
            // Drop FK before the unique index — MySQL won't drop an index backing a FK constraint
            $table->dropForeign(['division_id']);
            $table->dropUnique('rrm_unique_pairing');

            // Make away nullable to support byes
            $table->foreignId('away_enrolment_event_id')->nullable()->change();

            // Bracket structure columns
            $table->unsignedSmallInteger('round')->default(1)->after('home_result');
            $table->string('bracket', 20)->default('winners')->after('round');         // winners | losers | grand_final
            $table->unsignedSmallInteger('bracket_slot')->nullable()->after('bracket'); // position within round
        });

        // Re-add division_id FK backed by the plain index added above
        Schema::table('round_robin_matches', function (Blueprint $table) {
            $table->foreign('division_id')->references('id')->on('divisions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('round_robin_matches', function (Blueprint $table) {
            $table->dropColumn(['round', 'bracket', 'bracket_slot']);
            $table->foreignId('away_enrolment_event_id')->nullable(false)->change();
            $table->unique(['division_id', 'home_enrolment_event_id', 'away_enrolment_event_id'], 'rrm_unique_pairing');
        });
    }
};
