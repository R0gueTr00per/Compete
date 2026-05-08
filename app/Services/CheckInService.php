<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use Illuminate\Support\Collection;

class CheckInService
{
    public function __construct(
        private readonly DivisionAssignmentService $divisions
    ) {}

    public function checkIn(Enrolment $enrolment): void
    {
        $enrolment->update([
            'checked_in'    => true,
            'checked_in_at' => now(),
            'status'        => 'checked_in',
        ]);
    }

    public function undoCheckIn(Enrolment $enrolment): void
    {
        $enrolment->update([
            'checked_in'    => false,
            'checked_in_at' => null,
            'status'        => 'confirmed',
        ]);

        // Clear weight confirmations so they can be re-entered
        $enrolment->activeEvents()->update(['weight_confirmed_kg' => null]);
    }

    /**
     * Record confirmed weight once for an enrolment and apply it to every event
     * in that enrolment that requires a weight check, re-assigning divisions.
     * Returns enrolment events whose division changed (for warning display).
     *
     * @return \Illuminate\Support\Collection<EnrolmentEvent>  Events where division changed
     */
    public function confirmWeightForEnrolment(Enrolment $enrolment, float $weightKg): \Illuminate\Support\Collection
    {
        $changed = collect();

        $weightEvents = $enrolment->activeEvents()
            ->with(['competitionEvent.eventType', 'enrolment.competitor.competitorProfile'])
            ->get()
            ->filter(fn ($ee) => $ee->competitionEvent->eventType->requires_weight_check);

        foreach ($weightEvents as $ee) {
            $previousDivisionId = $ee->division_id;

            $ee->update(['weight_confirmed_kg' => $weightKg]);

            $division = $this->divisions->assignDivision($ee->fresh(['competitionEvent.eventType', 'enrolment.competitor.competitorProfile']));

            if ($division) {
                $ee->update(['division_id' => $division->id]);
            }

            if ($division?->id !== $previousDivisionId) {
                $changed->push($ee->fresh('division'));
            }
        }

        return $changed;
    }
}
