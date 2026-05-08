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
        Schema::table('enrolments', function (Blueprint $table) {
            $table->boolean('checked_in')->default(false)->after('status');
            $table->timestamp('checked_in_at')->nullable()->after('checked_in');
        });

        Schema::table('enrolment_events', function (Blueprint $table) {
            $table->dropColumn(['checked_in', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('enrolment_events', function (Blueprint $table) {
            $table->boolean('checked_in')->default(false);
            $table->timestamp('checked_in_at')->nullable();
        });

        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn(['checked_in', 'checked_in_at']);
        });
    }
};
