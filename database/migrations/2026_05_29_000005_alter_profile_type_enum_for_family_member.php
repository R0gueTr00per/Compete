<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE competitor_profiles MODIFY profile_type ENUM('self','family_member') NOT NULL DEFAULT 'self'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE competitor_profiles SET profile_type = 'child' WHERE profile_type = 'family_member'");
            DB::statement("ALTER TABLE competitor_profiles MODIFY profile_type ENUM('self','child') NOT NULL DEFAULT 'self'");
        }
    }
};
