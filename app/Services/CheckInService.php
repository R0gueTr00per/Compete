<?php

namespace App\Services;

use App\Models\Enrolment;
use App\Models\EnrolmentCheckIn;
use App\Models\EnrolmentEvent;
use Illuminate\Support\Collection;

class CheckInService
{
    public function __construct(
        private readonly DivisionAssignmentService $divisions
    ) {}

    public function checkIn(Enrolment $enrolment, int $dayId, ?float $weightKg = null): void
    {
        EnrolmentCheckIn::firstOrCreate(
            ['enrolment_id' => $enrolment->id, 'competition_day_id' => $dayId],
            [
                'checked_in_at' => now(),
                'weight_kg'     => $weightKg,
                'checked_in_by' => auth()->id(),
            ]
        );

        // Stamp first-ever check-in on the enrolment
        if (! $enrolment->checked_in_at) {
            $enrolment->update([
                'checked_in'    => true,
                'checked_in_at' => now(),
                'status'        => 'checked_in',
            ]);
        }
    }

    public function undoCheckIn(Enrolment $enrolment, int $dayId): void
    {
        EnrolmentCheckIn::where('enrolment_id', $enrolment->id)
            ->where('competition_day_id', $dayId)
            ->delete();

        // If no remaining check-ins, revert enrolment to pre-checked-in state
        if (! EnrolmentCheckIn::where('enrolment_id', $enrolment->id)->exists()) {
            $enrolment->update([
                'checked_in'    => false,
                'checked_in_at' => null,
                'status'        => 'confirmed',
            ]);

            // Clear weight confirmations so they can be re-entered next time
            $enrolment->activeEvents()->update(['weight_confirmed_kg' => null]);
        }
    }

    /**
     * Remove competitor from specific events after a weight mismatch (option 3 in mismatch modal).
     * Each entry must contain 'ee_id'.
     */
    public function cancelEventRegistration(array $changes): void
    {
        $eeIds = collect($changes)->pluck('ee_id');

        EnrolmentEvent::whereIn('id', $eeIds)->update([
            'removed'      => true,
            'removed_at'   => now(),
            'removed_by'   => auth()->id(),
            'removal_type' => 'weight_mismatch',
        ]);
    }

    /**
     * Save the confirmed weight, immediately assign new divisions, and return
     * what changed so the caller can prompt the user to accept or revert.
     * Each entry: ['ee_id', 'event_name', 'original_division_id', 'original_label', 'new_label']
     *
     * When $dayId is provided, only processes weight-bracket divisions on that day.
     */
    public function applyWeightWithDivisions(Enrolment $enrolment, float $weightKg, ?int $dayId = null): Collection
    {
        $changes = collect();

        $weightEvents = $enrolment->activeEvents()
            ->with(['competitionEvent', 'division'])
            ->get()
            ->filter(function ($ee) use ($dayId) {
                if ($dayId !== null) {
                    return $ee->division?->competition_day_id === $dayId
                        && $ee->division?->weight_class_id !== null;
                }

                return $ee->competitionEvent->requires_weight_check;
            });

        foreach ($weightEvents as $ee) {
            $originalDivisionId = $ee->division_id;
            $originalLabel      = $ee->division?->full_label ?? 'Unassigned';

            $ee->update(['weight_confirmed_kg' => $weightKg]);

            $newDivision = $this->divisions->assignDivision(
                $ee->fresh(['competitionEvent', 'enrolment.competitor'])
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

    public function revertWeight(Enrolment $enrolment, ?int $dayId = null): void
    {
        $query = $enrolment->activeEvents();

        if ($dayId !== null) {
            $query->whereHas('division', fn ($q) => $q
                ->where('competition_day_id', $dayId)
                ->whereNotNull('weight_class_id')
            );
        } else {
            $query->whereHas('competitionEvent', fn ($q) => $q->whereHas(
                'divisions', fn ($q2) => $q2->whereNotNull('weight_class_id')
            ));
        }

        $query->update(['weight_confirmed_kg' => null]);
    }
}
