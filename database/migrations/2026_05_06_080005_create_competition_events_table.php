<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained();
            $table->smallInteger('running_order')->unsigned()->nullable();
            $table->string('location_label', 50)->nullable();
            $table->tinyInteger('target_score')->unsigned()->nullable();
            $table->enum('status', ['scheduled', 'running', 'complete', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_events');
    }
};
