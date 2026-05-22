<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Models\Organisation;
use App\Services\CheckInService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Checks in all pending/confirmed enrolments for the Demo Tournament.
 *
 * For enrolments that include weight-based events (weight_sex, age_weight, age_weight_sex),
 * the competitor's weight_kg is confirmed first so division reassignment fires correctly.
 *
 * Safe to re-run — already-checked-in enrolments are skipped.
 */
class DemoCheckInSeeder extends Seeder
{
    public function __construct(private readonly CheckInService $checkInService) {}

    public function run(): void
    {
        Notification::fake();

        $org = Organisation::where('slug', 'like', 'demo%')->latest('id')->first();

        if (! $org) {
            $this->command->warn('No demo organisation found (slug starting with "demo"). Skipping.');
            return;
        }

        $competition = Competition::where('organisation_id', $org->id)
            ->where('name', 'Demo Tournament')
            ->first();

        if (! $competition) {
            $this->command->warn('Demo Tournament not found — run DemoCompetitionSeeder first.');
            return;
        }

        $enrolments = Enrolment::where('competition_id', $competition->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->with([
                'competitor',
                'activeEvents.competitionEvent',
                'activeEvents.division',
            ])
            ->get();

        if ($enrolments->isEmpty()) {
            $this->command->warn('No pending/confirmed enrolments found — run DemoEnrolmentSeeder first.');
            return;
        }

        $this->command->info("Found {$enrolments->count()} enrolment(s) to check in.");

        $checkedIn = 0;
        $skipped   = 0;

        foreach ($enrolments as $enrolment) {
            if ($enrolment->checked_in) {
                $skipped++;
                continue;
            }

            $fullName = $enrolment->competitor?->full_name ?? "enrolment #{$enrolment->id}";

            $needsWeight = $enrolment->activeEvents->contains(
                fn ($ee) => $ee->competitionEvent->requires_weight_check
            );

            if ($needsWeight) {
                $weight = $enrolment->weight_kg ? (float) $enrolment->weight_kg : 60.0;
                $this->checkInService->applyWeightWithDivisions($enrolment, $weight);
                $this->command->line("  {$fullName} — weight {$weight} kg confirmed");
            }

            $this->checkInService->checkIn($enrolment);
            $checkedIn++;
        }

        $suffix = $skipped > 0 ? ", {$skipped} already checked in (skipped)" : '';
        $this->command->info("Done: {$checkedIn} competitor(s) checked in{$suffix}.");
    }
}
