<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('planning', 'open', 'enrolments_closed', 'check_in', 'running', 'complete') DEFAULT 'planning'");
        DB::statement("UPDATE competitions SET status = 'enrolments_closed' WHERE status = 'closed'");
    }

    public function down(): void
    {
        DB::statement("UPDATE competitions SET status = 'closed' WHERE status = 'enrolments_closed'");
        DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('planning', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'planning'");
    }
};
