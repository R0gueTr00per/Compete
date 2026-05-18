<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill owner_user_id and profile_type on all existing profiles.
        DB::statement("UPDATE competitor_profiles SET owner_user_id = user_id, profile_type = 'self' WHERE owner_user_id IS NULL");

        // 2. Backfill users.first_name/surname from their existing self profile.
        //    SQLite does not support UPDATE … INNER JOIN — use correlated subqueries.
        DB::statement("
            UPDATE users
            SET first_name = (
                    SELECT first_name FROM competitor_profiles
                    WHERE competitor_profiles.user_id = users.id LIMIT 1
                ),
                surname = (
                    SELECT surname FROM competitor_profiles
                    WHERE competitor_profiles.user_id = users.id LIMIT 1
                )
            WHERE first_name IS NULL
              AND EXISTS (SELECT 1 FROM competitor_profiles WHERE competitor_profiles.user_id = users.id)
        ");

        // 3. Migrate enrolments.competitor_profile_id from competitor_id → profile id.
        DB::statement("
            UPDATE enrolments
            SET competitor_profile_id = (
                SELECT id FROM competitor_profiles
                WHERE competitor_profiles.user_id = enrolments.competitor_id LIMIT 1
            )
            WHERE competitor_profile_id IS NULL
        ");

        // 4. Drop the old unique constraint (idempotent — may already be gone from a prior partial run).
        //    MySQL uses this composite index as the backing index for the competition_id FK (leftmost prefix)
        //    and may also back the competitor_id FK — both must be dropped before the index can be removed.
        $indexes = collect(Schema::getIndexes('enrolments'))->pluck('name');
        $fks     = collect(Schema::getForeignKeys('enrolments'))->flatMap(fn ($fk) => $fk['columns']);
        if ($indexes->contains('enrolments_competition_id_competitor_id_unique')) {
            Schema::table('enrolments', function (Blueprint $table) use ($fks) {
                if ($fks->contains('competition_id')) {
                    $table->dropForeign(['competition_id']);
                }
                if ($fks->contains('competitor_id')) {
                    $table->dropForeign(['competitor_id']);
                }
                $table->dropUnique(['competition_id', 'competitor_id']);
            });

            // Re-add competition_id FK (competitor_id FK is permanently removed in step 7)
            Schema::table('enrolments', function (Blueprint $table) {
                $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
            });
        }

        // 5. Make competitor_profile_id NOT NULL (idempotent via change()).
        Schema::table('enrolments', function (Blueprint $table) {
            $table->unsignedBigInteger('competitor_profile_id')->nullable(false)->change();
        });

        // 6. Add FK and unique constraint if not already present.
        $fks     = collect(Schema::getForeignKeys('enrolments'))->flatMap(fn ($fk) => $fk['columns']);
        $indexes = collect(Schema::getIndexes('enrolments'))->pluck('name');

        if (! $fks->contains('competitor_profile_id')) {
            Schema::table('enrolments', function (Blueprint $table) {
                $table->foreign('competitor_profile_id')
                    ->references('id')
                    ->on('competitor_profiles')
                    ->restrictOnDelete();
            });
        }

        if (! $indexes->contains('enrolments_competition_id_competitor_profile_id_unique')) {
            Schema::table('enrolments', function (Blueprint $table) {
                $table->unique(['competition_id', 'competitor_profile_id']);
            });
        }

        // 7. Drop the old competitor_id column (and any indexes/FK on it) if it still exists.
        if (Schema::hasColumn('enrolments', 'competitor_id')) {
            $indexes = collect(Schema::getIndexes('enrolments'))->pluck('name');

            // Drop the performance index added in a prior migration before dropping the column.
            if ($indexes->contains('enrolments_competitor_id_index')) {
                Schema::table('enrolments', function (Blueprint $table) {
                    $table->dropIndex('enrolments_competitor_id_index');
                });
            }

            $remainingFks = collect(Schema::getForeignKeys('enrolments'))->flatMap(fn ($fk) => $fk['columns']);
            Schema::table('enrolments', function (Blueprint $table) use ($remainingFks) {
                if ($remainingFks->contains('competitor_id')) {
                    $table->dropForeign(['competitor_id']);
                }
                $table->dropColumn('competitor_id');
            });
        }

        // 8. Make owner_user_id NOT NULL now that every row is populated.
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_user_id')->nullable()->change();
        });

        if (! Schema::hasColumn('enrolments', 'competitor_id')) {
            Schema::table('enrolments', function (Blueprint $table) {
                $table->unsignedBigInteger('competitor_id')->nullable()->after('competition_id');
            });

            DB::statement("
                UPDATE enrolments
                SET competitor_id = (
                    SELECT user_id FROM competitor_profiles
                    WHERE competitor_profiles.id = enrolments.competitor_profile_id LIMIT 1
                )
            ");

            Schema::table('enrolments', function (Blueprint $table) {
                $table->unsignedBigInteger('competitor_id')->nullable(false)->change();
            });
        }

        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropForeign(['competitor_profile_id']);
            $table->dropUnique(['competition_id', 'competitor_profile_id']);
            $table->dropIndex(['competitor_profile_id']);
            $table->dropColumn('competitor_profile_id');

            $table->foreign('competitor_id')->references('id')->on('users');
            $table->unique(['competition_id', 'competitor_id']);
        });
    }
};
