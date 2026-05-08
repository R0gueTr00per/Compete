<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Models\User;
use App\Notifications\EnrolmentConfirmedNotification;
use App\Notifications\YakusukoPartnerEnrolledNotification;
use Illuminate\Support\Facades\DB;

class EnrolmentService
{
    public function __construct(
        private readonly DivisionAssignmentService $divisions
    ) {}

    /**
     * Enrol a competitor in a competition for one or more event type IDs.
     * Returns the Enrolment record (new or existing).
     */
    /**
     * @param  array<int,int>  $competitionEventIds  IDs of CompetitionEvents to add
     * @param  array<int,int>  $selectedDivisions    Optional map of competition_event_id => division_id chosen by the competitor
     */
    /**
     * @param  array<int,int>   $competitionEventIds
     * @param  array<int,int>   $selectedDivisions    competition_event_id => division_id
     * @param  array            $entryDetails         rank/weight/dojo to store on the enrolment
     */
    public function enrol(User $competitor, Competition $competition, array $competitionEventIds, array $selectedDivisions = [], array $entryDetails = []): Enrolment
    {
        return DB::transaction(function () use ($competitor, $competition, $competitionEventIds, $selectedDivisions, $entryDetails) {
            $isLate = $competition->isLateEnrolment();
            $fee = $this->calculateFee($competition, count($competitionEventIds), $isLate);

            $enrolmentData = array_merge([
                'enrolled_at'    => now(),
                'is_late'        => $isLate,
                'fee_calculated' => $fee,
                'status'         => 'pending',
            ], array_filter($entryDetails, fn ($v) => $v !== null && $v !== ''));

            $enrolment = Enrolment::firstOrCreate(
                ['competition_id' => $competition->id, 'competitor_id' => $competitor->id],
                $enrolmentData
            );

            // Update entry details if enrolment already existed
            if (! $enrolment->wasRecentlyCreated && ! empty($entryDetails)) {
                $enrolment->update(array_filter($entryDetails, fn ($v) => $v !== null && $v !== ''));
            }

            foreach ($competitionEventIds as $eventId) {
                // selectedDivisions[eventId] can be a single ID (legacy) or an array of IDs
                $chosen = $selectedDivisions[$eventId] ?? null;
                $divisionIds = $chosen ? array_filter((array) $chosen) : [];

                if (empty($divisionIds)) {
                    // No division pre-selected — create one EE and auto-assign
                    $alreadyBlank = $enrolment->enrolmentEvents()
                        ->where('competition_event_id', $eventId)
                        ->whereNull('division_id')
                        ->where('removed', false)
                        ->exists();
                    if (! $alreadyBlank) {
                        $ee = $enrolment->enrolmentEvents()->create([
                            'competition_event_id' => $eventId,
                            'yakusuko_complete'    => false,
                            'removed'              => false,
                        ]);
                        $division = $this->divisions->assignDivision($ee);
                        if ($division) {
                            $ee->update(['division_id' => $division->id]);
                        }
                    }
                } else {
                    // Create one EE per selected division, skipping duplicates
                    foreach ($divisionIds as $divisionId) {
                        $division = Division::find($divisionId);
                        if (! $division || $division->competition_event_id !== (int) $eventId) {
                            continue;
                        }
                        $alreadyIn = $enrolment->enrolmentEvents()
                            ->where('competition_event_id', $eventId)
                            ->where('division_id', $divisionId)
                            ->where('removed', false)
                            ->exists();
                        if ($alreadyIn) {
                            continue;
                        }
                        $enrolment->enrolmentEvents()->create([
                            'competition_event_id' => $eventId,
                            'division_id'          => $divisionId,
                            'yakusuko_complete'    => false,
                            'removed'              => false,
                        ]);
                    }
                }
            }

            // Recalculate fee based on total active events
            $totalEvents = $enrolment->activeEvents()->count();
            $enrolment->update([
                'fee_calculated' => $this->calculateFee($competition, $totalEvents, $enrolment->is_late),
                'is_late'        => $isLate,
            ]);

            $competitor->notify(new EnrolmentConfirmedNotification($enrolment));

            return $enrolment;
        });
    }

    /**
     * Link two EnrolmentEvents as Yakusuko partners.
     * Sets yakusuko_complete = true on both once both sides are enrolled.
     */
    public function resolveYakusukoPartner(EnrolmentEvent $ee, EnrolmentEvent $partnerEe): void
    {
        $ee->update(['partner_enrolment_event_id' => $partnerEe->id]);
        $partnerEe->update(['partner_enrolment_event_id' => $ee->id]);

        $ee->update(['yakusuko_complete' => true]);
        $partnerEe->update(['yakusuko_complete' => true]);

        // Notify both competitors
        $ee->load(['enrolment.competitor', 'competitionEvent.eventType', 'competitionEvent.competition']);
        $partnerEe->load(['enrolment.competitor', 'competitionEvent.eventType']);

        $competition = $ee->competitionEvent->competition;
        $event       = $ee->competitionEvent;

        $eeUser      = $ee->enrolment->competitor;
        $partnerUser = $partnerEe->enrolment->competitor;

        if ($eeUser && $partnerUser) {
            $eeUser->notify(new YakusukoPartnerEnrolledNotification($competition, $event, $partnerUser));
            $partnerUser->notify(new YakusukoPartnerEnrolledNotification($competition, $event, $eeUser));
        }
    }

    /**
     * Remove a competitor from a specific event (soft-remove with reason).
     */
    public function removeParticipant(EnrolmentEvent $ee, User $removedBy, string $reason): void
    {
        DB::transaction(function () use ($ee, $removedBy, $reason) {
            $ee->update([
                'removed'        => true,
                'removed_at'     => now(),
                'removed_by'     => $removedBy->id,
                'removal_reason' => $reason,
            ]);

            // Recalculate fee for the enrolment
            $enrolment = $ee->enrolment;
            $totalEvents = $enrolment->activeEvents()->count();
            $enrolment->update([
                'fee_calculated' => $this->calculateFee(
                    $enrolment->competition,
                    $totalEvents,
                    $enrolment->is_late
                ),
            ]);
        });
    }

    /**
     * Re-add a previously removed competitor to an event.
     */
    public function readdParticipant(EnrolmentEvent $ee): void
    {
        DB::transaction(function () use ($ee) {
            $ee->update([
                'removed'        => false,
                'removed_at'     => null,
                'removed_by'     => null,
                'removal_reason' => null,
            ]);

            $enrolment = $ee->enrolment;
            $totalEvents = $enrolment->activeEvents()->count();
            $enrolment->update([
                'fee_calculated' => $this->calculateFee(
                    $enrolment->competition,
                    $totalEvents,
                    $enrolment->is_late
                ),
            ]);
        });
    }

    public function calculateFee(Competition $competition, int $eventCount, bool $isLate): float
    {
        if ($eventCount <= 0) {
            return 0.0;
        }

        $fee = $competition->fee_first_event
            + ($eventCount - 1) * $competition->fee_additional_event;

        if ($isLate) {
            $fee += $competition->late_surcharge;
        }

        return (float) $fee;
    }
}
