<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->tinyInteger('overtime_rounds')->unsigned()->nullable()->after('tiebreak_mode');
        });

        DB::table('competition_events')->update(['overtime_rounds' => 1]);
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn('overtime_rounds');
        });
    }
};
