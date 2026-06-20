<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolment_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_day_id')->constrained()->cascadeOnDelete();
            $table->datetime('checked_in_at');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enrolment_id', 'competition_day_id']);
        });

        // Backfill: one record per existing checked-in enrolment, assigned to competition's first day
        $checkedIn = DB::table('enrolments')
            ->whereNotNull('checked_in_at')
            ->whereNull('deleted_at')
            ->select('id', 'competition_id', 'checked_in_at')
            ->get();

        foreach ($checkedIn as $enrolment) {
            $firstDay = DB::table('competition_days')
                ->where('competition_id', $enrolment->competition_id)
                ->orderBy('date')
                ->first();

            if (! $firstDay) {
                continue;
            }

            DB::table('enrolment_check_ins')->insertOrIgnore([
                'enrolment_id'       => $enrolment->id,
                'competition_day_id' => $firstDay->id,
                'checked_in_at'      => $enrolment->checked_in_at,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolment_check_ins');
    }
};
