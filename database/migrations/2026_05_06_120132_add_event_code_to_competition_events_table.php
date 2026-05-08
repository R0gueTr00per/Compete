<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->string('event_code', 10)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn('event_code');
        });
    }
};
