<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn([
                'bracket_seed_by_rank',
                'bracket_match_similar_age',
                'bracket_match_similar_weight',
            ]);
            $table->string('bracket_first_round_order')->nullable()->after('bracket_sort');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn('bracket_first_round_order');
            $table->boolean('bracket_seed_by_rank')->default(false);
            $table->boolean('bracket_match_similar_age')->default(false);
            $table->boolean('bracket_match_similar_weight')->default(false);
        });
    }
};
