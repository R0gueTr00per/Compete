<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_robin_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_enrolment_event_id')->constrained('enrolment_events')->cascadeOnDelete();
            $table->foreignId('away_enrolment_event_id')->constrained('enrolment_events')->cascadeOnDelete();
            $table->enum('home_result', ['win', 'loss', 'draw'])->nullable();
            $table->timestamps();

            $table->unique(['division_id', 'home_enrolment_event_id', 'away_enrolment_event_id'], 'rrm_unique_pairing');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_robin_matches');
    }
};
