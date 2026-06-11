<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('competition_messages', 'sort_order')) {
            Schema::table('competition_messages', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('message');
            });
        }

        // Seed sort_order for existing rows based on creation order per competition
        // MySQL requires wrapping the subquery in a derived table to avoid the
        // "can't specify target table for update in FROM clause" restriction.
        DB::statement('
            UPDATE competition_messages
            SET sort_order = (
                SELECT cnt FROM (
                    SELECT COUNT(*) AS cnt
                    FROM competition_messages older
                    WHERE older.competition_id = competition_messages.competition_id
                      AND (older.created_at < competition_messages.created_at
                           OR (older.created_at = competition_messages.created_at AND older.id < competition_messages.id))
                ) AS sub
            )
        ');
    }

    public function down(): void
    {
        Schema::table('competition_messages', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
