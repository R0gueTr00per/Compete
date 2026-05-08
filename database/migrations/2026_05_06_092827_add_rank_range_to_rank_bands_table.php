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
        Schema::table('rank_bands', function (Blueprint $table) {
            // Normalized rank scale: 9th kyu = -9 … 1st kyu = -1, 1st dan = 1 … 10th dan = 10, experience = 0
            // null means no bound on that end (open)
            $table->smallInteger('rank_min')->nullable()->after('description');
            $table->smallInteger('rank_max')->nullable()->after('rank_min');
        });
    }

    public function down(): void
    {
        Schema::table('rank_bands', function (Blueprint $table) {
            $table->dropColumn(['rank_min', 'rank_max']);
        });
    }
};
