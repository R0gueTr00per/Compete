<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });

        // Drop the simple unique constraint — application logic prevents active duplicates.
        // Soft-deleted (replaced) enrolments stay in their original cart for full history.
        $indexes = collect(Schema::getIndexes('enrolments'))->pluck('name');
        if ($indexes->contains('enrolments_competition_id_competitor_profile_id_unique')) {
            Schema::table('enrolments', function (Blueprint $table) {
                $table->dropUnique(['competition_id', 'competitor_profile_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique(['competition_id', 'competitor_profile_id']);
        });
    }
};
