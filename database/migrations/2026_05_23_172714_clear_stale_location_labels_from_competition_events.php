<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear location_label on any competition event whose label does not
        // exist in competition_locations for the same competition. These were
        // written by the seeder and were never valid user-assigned locations.
        DB::table('competition_events')
            ->whereNotNull('location_label')
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('competition_locations')
                ->whereColumn('competition_locations.competition_id', 'competition_events.competition_id')
                ->whereColumn('competition_locations.name', 'competition_events.location_label')
            )
            ->update(['location_label' => null]);
    }

    public function down(): void {}
};
