<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE competitor_profiles SET profile_type = 'family_member' WHERE profile_type = 'child'");
    }

    public function down(): void
    {
        DB::statement("UPDATE competitor_profiles SET profile_type = 'child' WHERE profile_type = 'family_member'");
    }
};
