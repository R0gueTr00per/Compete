<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('competition_event_penalty_reasons');
    }

    public function down(): void
    {
        Schema::create('competition_event_penalty_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_event_id')->constrained()->cascadeOnDelete();
            $table->enum('penalty_type', ['warn', 'dq', 'forfeit']);
            $table->string('reason');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
