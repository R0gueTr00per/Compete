<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judge_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('judge_number')->unsigned();
            $table->decimal('score', 5, 3);
            $table->timestamps();

            $table->unique(['result_id', 'judge_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_scores');
    }
};
