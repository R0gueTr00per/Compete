<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add 'planning' alongside 'draft' so existing rows are valid
        DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('draft', 'planning', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'planning'");
        // Step 2: migrate existing data
        DB::table('competitions')->where('status', 'draft')->update(['status' => 'planning']);
        // Step 3: remove 'draft' now that no rows use it
        DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('planning', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'planning'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('planning', 'draft', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'draft'");
        DB::table('competitions')->where('status', 'planning')->update(['status' => 'draft']);
        DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('draft', 'open', 'closed', 'check_in', 'running', 'complete') DEFAULT 'draft'");
    }
};
