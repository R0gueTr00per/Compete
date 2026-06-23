<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('enrolments')
            ->where('status', 'checked_in')
            ->update(['status' => 'confirmed']);
    }

    public function down(): void
    {
        // Re-stamp status for enrolments that have at least one check-in record
        DB::table('enrolments')
            ->where('status', 'confirmed')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('enrolment_check_ins')
                ->whereColumn('enrolment_check_ins.enrolment_id', 'enrolments.id')
            )
            ->update(['status' => 'checked_in']);
    }
};
