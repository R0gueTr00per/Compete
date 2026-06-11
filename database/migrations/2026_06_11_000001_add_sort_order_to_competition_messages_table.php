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
        DB::statement('
            UPDATE competition_messages
            SET sort_order = (
                SELECT COUNT(*)
                FROM competition_messages older
                WHERE older.competition_id = competition_messages.competition_id
                  AND (older.created_at < competition_messages.created_at
                       OR (older.created_at = competition_messages.created_at AND older.id < competition_messages.id))
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
