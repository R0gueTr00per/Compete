<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitions')->where('status', 'check_in')->update(['status' => 'running']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('planning','advertise','open','enrolments_closed','running','complete') DEFAULT 'planning'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE competitions MODIFY COLUMN status ENUM('planning','advertise','open','enrolments_closed','check_in','running','complete') DEFAULT 'planning'");
        }
    }
};
