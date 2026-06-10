<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dateTime('planned_start_at')->nullable()->after('running_order');
            $table->dateTime('actual_start_at')->nullable()->after('planned_start_at');
            $table->dateTime('actual_end_at')->nullable()->after('actual_start_at');
        });

        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn(['planned_start_at', 'actual_start_at', 'actual_end_at']);
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['planned_start_at', 'actual_start_at', 'actual_end_at']);
        });
    }
};
