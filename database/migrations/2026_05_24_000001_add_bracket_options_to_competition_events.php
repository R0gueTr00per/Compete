<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->string('bracket_sort')->default('first_name')->after('manual_pairing');
            $table->boolean('bracket_seed_by_rank')->default(false)->after('bracket_sort');
            $table->boolean('bracket_match_similar_age')->default(false)->after('bracket_seed_by_rank');
            $table->boolean('bracket_match_similar_weight')->default(false)->after('bracket_match_similar_age');
            $table->boolean('bracket_prefer_different_dojo')->default(false)->after('bracket_match_similar_weight');
            $table->boolean('bracket_avoid_repeat_matchups')->default(false)->after('bracket_prefer_different_dojo');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn([
                'bracket_sort',
                'bracket_seed_by_rank',
                'bracket_match_similar_age',
                'bracket_match_similar_weight',
                'bracket_prefer_different_dojo',
                'bracket_avoid_repeat_matchups',
            ]);
        });
    }
};
