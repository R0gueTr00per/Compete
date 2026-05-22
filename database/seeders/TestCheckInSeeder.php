<?php

namespace Database\Seeders;

use App\Models\Enrolment;
use App\Services\CheckInService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class TestCheckInSeeder extends Seeder
{
    public function run(): void
    {
        Notification::fake();

        $service = app(CheckInService::class);

        $enrolments = Enrolment::whereIn('status', ['pending', 'confirmed'])
            ->with([
                'competitor',
                'activeEvents.competitionEvent',
                'activeEvents.division',
            ])
            ->get();

        if ($enrolments->isEmpty()) {
            $this->command->warn('No pending/confirmed enrolments found.');
            return;
        }

        $this->command->info("Found {$enrolments->count()} enrolment(s) to check in.");

        $checkedIn = 0;

        foreach ($enrolments as $enrolment) {
            $fullName = $enrolment->competitor?->full_name ?? "#{$enrolment->id}";

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
