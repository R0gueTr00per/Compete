<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->renameColumn('minutes_per_competitor', 'seconds_per_competitor');
            $table->renameColumn('transition_padding_minutes', 'transition_padding_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->renameColumn('seconds_per_competitor', 'minutes_per_competitor');
            $table->renameColumn('transition_padding_seconds', 'transition_padding_minutes');
        });
    }
};
