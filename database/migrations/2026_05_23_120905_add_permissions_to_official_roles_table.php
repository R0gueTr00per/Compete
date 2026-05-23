<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('official_roles', function (Blueprint $table) {
            $table->boolean('can_access_enrolments')->default(false)->after('name');
            $table->boolean('can_access_checkin')->default(false)->after('can_access_enrolments');
            $table->boolean('can_access_create_enrolment')->default(false)->after('can_access_checkin');
            $table->boolean('can_access_scoring')->default(false)->after('can_access_create_enrolment');
        });
    }

    public function down(): void
    {
        Schema::table('official_roles', function (Blueprint $table) {
            $table->dropColumn([
                'can_access_enrolments',
                'can_access_checkin',
                'can_access_create_enrolment',
                'can_access_scoring',
            ]);
        });
    }
};
