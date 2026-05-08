<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrolment_event_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('placement')->unsigned()->nullable();
            $table->boolean('placement_overridden')->default(false);
            $table->decimal('total_score', 7, 3)->nullable();
            $table->enum('win_loss', ['win', 'loss', 'draw'])->nullable();
            $table->boolean('disqualified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
