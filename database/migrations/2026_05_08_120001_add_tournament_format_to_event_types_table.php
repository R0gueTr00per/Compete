<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->enum('tournament_format', ['once_off', 'single_elimination', 'double_elimination'])
                ->default('once_off')
                ->after('is_round_robin');
        });

        // Migrate existing is_round_robin=true rows to single_elimination
        \Illuminate\Support\Facades\DB::table('event_types')
            ->where('is_round_robin', true)
            ->update(['tournament_format' => 'single_elimination']);

        Schema::table('event_types', function (Blueprint $table) {
            $table->dropColumn('is_round_robin');
        });
    }

    public function down(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->boolean('is_round_robin')->default(false)->after('judge_count');
            $table->dropColumn('tournament_format');
        });
    }
};
