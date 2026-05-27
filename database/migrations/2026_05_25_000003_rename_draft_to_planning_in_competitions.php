<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        if ($mysql) {
            DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('draft', 'planning', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'planning'");
        }

        DB::table('competitions')->where('status', 'draft')->update(['status' => 'planning']);

        if ($mysql) {
            DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('planning', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'planning'");
        }
    }

    public function down(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        if ($mysql) {
            DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('planning', 'draft', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'draft'");
        }

        DB::table('competitions')->where('status', 'planning')->update(['status' => 'draft']);

        if ($mysql) {
            DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('draft', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'draft'");
        }
    }
};
