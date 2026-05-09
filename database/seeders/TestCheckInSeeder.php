<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Services\CheckInService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class TestCheckInSeeder extends Seeder
{
    public function run(): void
    {
        Notification::fake();

        $competition = Competition::orderByDesc('competition_date')->first();

        if (! $competition) {
            $this->command->error('No competition found.');
            return;
        }

        $this->command->info("Checking in competitors for: {$competition->name}");

        $service = app(CheckInService::class);

        $enrolments = Enrolment::where('competition_id', $competition->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('activeEvents')
            ->with([
                'competitor.competitorProfile',
                'activeEvents.competitionEvent',
                'activeEvents.division',
            ])
            ->get();

        if ($enrolments->isEmpty()) {
            $this->command->warn('No pending/confirmed enrolments found.');
            return;
        }

        $checkedIn = 0;

        foreach ($enrolments as $enrolment) {
            $profile  = $enrolment->competitor?->competitorProfile;
            $fullName = $profile
                ? "{$profile->first_name} {$profile->surname}"
                : $enrolment->competitor?->name ?? "#{$enrolment->id}";

            // Confirm weight for events that require it, using enrolled weight
            $needsWeight = $enrolment->activeEvents->contains(
                fn ($ee) => $ee->competitionEvent->requires_weight_check
            );

            if ($needsWeight) {
                $weight = $enrolment->weight_kg ? (float) $enrolment->weight_kg : 60.0;
                $service->applyWeightWithDivisions($enrolment, $weight);
                $this->command->line("  {$fullName} — weight {$weight} kg confirmed");
            }

            $service->checkIn($enrolment);
            $checkedIn++;
            $this->command->info("  {$fullName} — checked in");
        }

        $this->command->info("Done. {$checkedIn} competitor(s) checked in.");
    }
}
