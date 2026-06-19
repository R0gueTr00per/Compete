<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->foreignId('competition_day_id')
                ->nullable()
                ->after('competition_event_id')
                ->constrained('competition_days')
                ->nullOnDelete();
        });

        // Backfill: assign each division to its competition's single existing day
        DB::table('competition_days')->orderBy('id')->get()->each(function ($day) {
            $divisionIds = DB::table('divisions')
                ->join('competition_events', 'competition_events.id', '=', 'divisions.competition_event_id')
                ->where('competition_events.competition_id', $day->competition_id)
                ->pluck('divisions.id');

            if ($divisionIds->isNotEmpty()) {
                DB::table('divisions')
                    ->whereIn('id', $divisionIds->toArray())
                    ->update(['competition_day_id' => $day->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropForeign(['competition_day_id']);
            $table->dropColumn('competition_day_id');
        });
    }
};
