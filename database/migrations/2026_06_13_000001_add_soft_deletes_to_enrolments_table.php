<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: MySQL DDL auto-commits, so a prior partial run may have already added deleted_at
        if (! Schema::hasColumn('enrolments', 'deleted_at')) {
            Schema::table('enrolments', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        // Drop the simple unique constraint — application logic prevents active duplicates.
        // Soft-deleted (replaced) enrolments stay in their original cart for full history.
        //
        // MySQL requires FK-backing indexes to exist before the composite unique index can be
        // dropped (error 1553). Add plain indexes on each FK column first if not already present.
        $indexes = collect(Schema::getIndexes('enrolments'))->pluck('name');

        if ($indexes->contains('enrolments_competition_id_competitor_profile_id_unique')) {
            Schema::table('enrolments', function (Blueprint $table) use ($indexes) {
                if (! $indexes->contains('enrolments_competition_id_index')) {
                    $table->index('competition_id');
                }
                if (! $indexes->contains('enrolments_competitor_profile_id_index')) {
                    $table->index('competitor_profile_id');
                }
            });

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
