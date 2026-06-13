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
        Schema::table('enrolment_events', function (Blueprint $table) {
            $table->enum('removal_type', ['user_withdrawn', 'admin_cancelled'])->nullable()->after('removal_reason');
            $table->foreignId('previous_division_id')->nullable()->constrained('divisions')->nullOnDelete()->after('division_id');
        });
    }

    public function down(): void
    {
        Schema::table('enrolment_events', function (Blueprint $table) {
            $table->dropForeign(['previous_division_id']);
            $table->dropColumn(['removal_type', 'previous_division_id']);
        });
    }
};
