<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_roles', function (Blueprint $table) {
            $table->foreignId('organisation_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        // Backfill: all existing roles belong to LFP (they're only used by LFP competitions)
        $lfpId = DB::table('organisations')->where('slug', 'lfp')->value('id');
        if ($lfpId) {
            DB::table('official_roles')->update(['organisation_id' => $lfpId]);
        }
    }

    public function down(): void
    {
        Schema::table('official_roles', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->dropColumn('organisation_id');
        });
    }
};
