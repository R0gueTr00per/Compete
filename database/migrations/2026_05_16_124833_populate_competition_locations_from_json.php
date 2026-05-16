<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitions')->whereNotNull('locations')->get()->each(function ($comp) {
            $locations = json_decode($comp->locations, true) ?? [];
            foreach ($locations as $i => $entry) {
                $name = is_array($entry) ? ($entry['location'] ?? '') : (string) $entry;
                if (! trim($name)) {
                    continue;
                }
                DB::table('competition_locations')->insert([
                    'competition_id' => $comp->id,
                    'name'           => trim($name),
                    'sort_order'     => $i,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        });
    }

    public function down(): void {}
};
