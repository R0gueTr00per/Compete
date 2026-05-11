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
     * Save the confirmed weight, immediately assign new divisions, and return
     * what changed so the caller can prompt the user to accept or revert.
     * Each entry: ['ee_id', 'event_name', 'original_division_id', 'original_label', 'new_label']
     */
    public function applyWeightWithDivisions(Enrolment $enrolment, float $weightKg): \Illuminate\Support\Collection
    {
        $changes = collect();

        $weightEvents = $enrolment->activeEvents()
            ->with(['competitionEvent', 'division'])
            ->get()
            ->filter(fn ($ee) => $ee->competitionEvent->requires_weight_check);

        foreach ($weightEvents as $ee) {
            $originalDivisionId    = $ee->division_id;
            $originalLabel         = $ee->division?->full_label ?? 'Unassigned';

            $ee->update(['weight_confirmed_kg' => $weightKg]);

            $newDivision = $this->divisions->assignDivision(
                $ee->fresh(['competitionEvent', 'enrolment.competitor.competitorProfile'])
            );

            $ee->update(['division_id' => $newDivision?->id]);

            if ($newDivision?->id !== $originalDivisionId) {
                $changes->push([
                    'ee_id'                => $ee->id,
                    'event_name'           => $ee->competitionEvent->name,
                    'original_division_id' => $originalDivisionId,
                    'original_label'       => $originalLabel,
                    'new_label'            => $newDivision?->full_label ?? 'Unassigned',
                ]);
            }
        }

        return $changes;
    }

    public function revertDivisionChanges(array $changes): void
    {
        foreach ($changes as $change) {
            EnrolmentEvent::find($change['ee_id'])
                ?->update(['division_id' => $change['original_division_id']]);
        }
    }

    public function revertWeight(Enrolment $enrolment): void
    {
        $enrolment->activeEvents()
            ->whereHas('competitionEvent', fn ($q) => $q->whereHas('divisions', fn ($q) => $q->whereNotNull('weight_class_id')))
            ->update(['weight_confirmed_kg' => null]);
    }
}
