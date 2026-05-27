<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn('awarded_places');
            $table->tinyInteger('awarded_places_2')->default(2)->after('status');
            $table->tinyInteger('awarded_places_3')->default(3)->after('awarded_places_2');
            $table->tinyInteger('awarded_places_4plus')->default(3)->after('awarded_places_3');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn(['awarded_places_2', 'awarded_places_3', 'awarded_places_4plus']);
            $table->string('awarded_places')->default('podium')->after('status');
        });
    }
};
