<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            ['code' => 'KA', 'name' => 'Kata',                'filter' => 'age_rank'],
            ['code' => 'TB', 'name' => 'Tile Breaking',       'filter' => 'age_rank'],
            ['code' => 'YA', 'name' => 'Yakusuko',            'filter' => 'age_only'],
            ['code' => 'SC', 'name' => 'Semi Contact',        'filter' => 'age_sex'],
            ['code' => 'PS', 'name' => 'Point Sparring',      'filter' => 'age_rank_sex'],
            ['code' => 'CS', 'name' => 'Continuous Sparring', 'filter' => 'age_rank_sex'],
            ['code' => 'SW', 'name' => 'Sumo',                'filter' => 'weight_sex'],
        ];

        foreach ($map as $entry) {
            DB::table('competition_events')
                ->where(fn ($q) => $q->where('event_code', $entry['code'])->orWhere('name', $entry['name']))
                ->whereNull('division_filter')
                ->update(['division_filter' => $entry['filter']]);
        }
    }

    public function down(): void
    {
        DB::table('competition_events')
            ->where(fn ($q) => $q
                ->whereIn('event_code', ['KA', 'TB', 'YA', 'SC', 'PS', 'CS', 'SW'])
                ->orWhereIn('name', ['Kata', 'Tile Breaking', 'Yakusuko', 'Semi Contact', 'Point Sparring', 'Continuous Sparring', 'Sumo'])
            )
            ->update(['division_filter' => null]);
    }
};
