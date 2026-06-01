<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->json('increment_buttons')->nullable()->after('target_score');
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn('increment_buttons');
        });
    }
};
