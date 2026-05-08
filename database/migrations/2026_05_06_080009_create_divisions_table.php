<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('age_band_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rank_band_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('weight_class_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('sex', ['M', 'F', 'mixed'])->nullable();
            $table->string('label', 255);
            $table->tinyInteger('target_score')->unsigned()->nullable();
            $table->enum('status', ['pending', 'assigned', 'complete', 'cancelled', 'combined'])->default('pending');
            $table->foreignId('combined_into_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
