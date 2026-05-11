<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename roles
        DB::table('roles')->where('name', 'competitor')->update(['name' => 'user']);
        DB::table('roles')->where('name', 'contributor')->update(['name' => 'competition_official']);
        DB::table('roles')->where('name', 'admin')->update(['name' => 'competition_administrator']);

        // Drop height_cm from competitor_profiles
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->dropColumn('height_cm');
        });

        // Clear Spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'user')->update(['name' => 'competitor']);
        DB::table('roles')->where('name', 'competition_official')->update(['name' => 'contributor']);
        DB::table('roles')->where('name', 'competition_administrator')->update(['name' => 'admin']);

        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->smallInteger('height_cm')->unsigned()->nullable();
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
