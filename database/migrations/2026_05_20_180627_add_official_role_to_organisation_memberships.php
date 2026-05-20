<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE organisation_memberships MODIFY COLUMN role ENUM('competitor', 'administrator', 'official') DEFAULT 'competitor'");
        } else {
            Schema::table('organisation_memberships', function (Blueprint $table) {
                $table->enum('role', ['competitor', 'administrator', 'official'])->default('competitor')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE organisation_memberships MODIFY COLUMN role ENUM('competitor', 'administrator') DEFAULT 'competitor'");
        } else {
            Schema::table('organisation_memberships', function (Blueprint $table) {
                $table->enum('role', ['competitor', 'administrator'])->default('competitor')->change();
            });
        }
    }
};
