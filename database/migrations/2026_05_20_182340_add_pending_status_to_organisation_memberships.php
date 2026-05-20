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
            DB::statement("ALTER TABLE organisation_memberships MODIFY COLUMN status ENUM('active', 'invited', 'suspended', 'pending') DEFAULT 'active'");
        } else {
            Schema::table('organisation_memberships', function (Blueprint $table) {
                $table->enum('status', ['active', 'invited', 'suspended', 'pending'])->default('active')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE organisation_memberships MODIFY COLUMN status ENUM('active', 'invited', 'suspended') DEFAULT 'active'");
        } else {
            Schema::table('organisation_memberships', function (Blueprint $table) {
                $table->enum('status', ['active', 'invited', 'suspended'])->default('active')->change();
            });
        }
    }
};
