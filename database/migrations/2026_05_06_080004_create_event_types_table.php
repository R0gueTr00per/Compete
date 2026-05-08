<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('scoring_method', 20);
            $table->string('division_filter', 30);
            $table->boolean('requires_partner')->default(false);
            $table->boolean('requires_weight_check')->default(false);
            $table->tinyInteger('default_target_score')->unsigned()->nullable();
            $table->tinyInteger('judge_count')->unsigned()->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
