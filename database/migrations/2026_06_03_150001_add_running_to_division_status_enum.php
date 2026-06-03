<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE divisions MODIFY COLUMN status ENUM('pending', 'assigned', 'running', 'complete', 'cancelled', 'combined') NOT NULL DEFAULT 'pending'");
        }
        // SQLite (local dev) has no native enum — string column already accepts any value
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE divisions SET status = 'assigned' WHERE status = 'running'");
            DB::statement("ALTER TABLE divisions MODIFY COLUMN status ENUM('pending', 'assigned', 'complete', 'cancelled', 'combined') NOT NULL DEFAULT 'pending'");
        }
    }
};
