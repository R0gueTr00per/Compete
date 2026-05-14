<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judge_scores', function (Blueprint $table) {
            $table->boolean('is_tiebreaker')->default(false)->after('judge_number');
            $table->dropUnique(['result_id', 'judge_number']);
            $table->unique(['result_id', 'judge_number', 'is_tiebreaker']);
        });
    }

    public function down(): void
    {
        Schema::table('judge_scores', function (Blueprint $table) {
            $table->dropUnique(['result_id', 'judge_number', 'is_tiebreaker']);
            $table->dropColumn('is_tiebreaker');
            $table->unique(['result_id', 'judge_number']);
        });
    }
};
