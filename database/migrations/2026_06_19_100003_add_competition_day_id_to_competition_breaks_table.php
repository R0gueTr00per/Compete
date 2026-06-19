<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_breaks', function (Blueprint $table) {
            $table->foreignId('competition_day_id')
                ->nullable()
                ->after('competition_id')
                ->constrained('competition_days')
                ->cascadeOnDelete();
        });

        // Backfill: assign each break to its competition's single existing day
        DB::table('competition_days')->orderBy('id')->get()->each(function ($day) {
            DB::table('competition_breaks')
                ->where('competition_id', $day->competition_id)
                ->whereNull('competition_day_id')
                ->update(['competition_day_id' => $day->id]);
        });
    }

    public function down(): void
    {
        Schema::table('competition_breaks', function (Blueprint $table) {
            $table->dropForeign(['competition_day_id']);
            $table->dropColumn('competition_day_id');
        });
    }
};
