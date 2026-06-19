<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
        });

        // Backfill: one row per existing competition that has a date
        DB::table('competitions')
            ->whereNotNull('competition_date')
            ->get(['id', 'competition_date', 'start_time', 'end_time'])
            ->each(function ($comp) {
                DB::table('competition_days')->insert([
                    'competition_id' => $comp->id,
                    'date'           => $comp->competition_date,
                    'start_time'     => $comp->start_time,
                    'end_time'       => $comp->end_time,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_days');
    }
};
