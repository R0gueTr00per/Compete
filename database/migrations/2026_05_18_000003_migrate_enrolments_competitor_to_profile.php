<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            // Add the new FK column alongside the old one (old stays until data is migrated)
            $table->unsignedBigInteger('competitor_profile_id')->nullable()->after('competitor_id');
            $table->index('competitor_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropIndex(['competitor_profile_id']);
            $table->dropColumn('competitor_profile_id');
        });
    }
};
