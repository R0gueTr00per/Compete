<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judge_score_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judge_score_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 8, 3);
            $table->timestamps();

            $table->unique(['judge_score_id', 'score_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_score_details');
    }
};
