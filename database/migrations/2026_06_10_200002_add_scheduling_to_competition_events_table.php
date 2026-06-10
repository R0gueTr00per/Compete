<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutes_per_competitor')->nullable()->after('default_max_competitors');
            $table->unsignedSmallInteger('transition_padding_minutes')->nullable()->after('minutes_per_competitor');
            $table->datetime('planned_start_at')->nullable()->after('transition_padding_minutes');
            $table->datetime('actual_start_at')->nullable()->after('planned_start_at');
            $table->datetime('actual_end_at')->nullable()->after('actual_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn([
                'minutes_per_competitor',
                'transition_padding_minutes',
                'planned_start_at',
                'actual_start_at',
                'actual_end_at',
            ]);
        });
    }
};
