<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Notifications\EnrolmentConfirmedNotification;
use App\Notifications\YakusukoPartnerEnrolledNotification;
use Illuminate\Support\Facades\DB;

class EnrolmentService
{
    public function __construct(
        private readonly DivisionAssignmentService $divisions
    ) {}

    /**
     * Enrol a competitor profile in a competition for one or more competition event IDs.
     * Returns the Enrolment record (new or existing).
     *
     * @param  array<int,int>   $competitionEventIds
     * @param  array<int,int>   $selectedDivisions    competition_event_id => division_id
     * @param  array            $entryDetails         rank/weight/dojo to store on the enrolment
     */
    public function enrol(CompetitorProfile $competitor, Competition $competition, array $competitionEventIds, array $selectedDivisions = [], array $entryDetails = []): Enrolment
    {
        return DB::transaction(function () use ($competitor, $competition, $competitionEventIds, $selectedDivisions, $entryDetails) {
            $isLate = $competition->isLateEnrolment();
            $fee = $this->calculateFee($competition, count($competitionEventIds), $isLate);

            $fillableDetails = array_filter($entryDetails, fn ($v) => $v !== null && $v !== '');

            $enrolment = Enrolment::firstOrNew(
                ['competition_id' => $competition->id, 'competitor_profile_id' => $competitor->id]
            );

            if (! $enrolment->exists) {
                $enrolment->fill(array_merge(['enrolled_at' => now(), 'is_late' => $isLate, 'status' => 'pending'], $fillableDetails));
                $enrolment->forceFill(['fee_calculated' => $fee])->save();
            } elseif (! empty($fillableDetails)) {
                $enrolment->fill($fillableDetails)->save();
            }

            foreach ($competitionEventIds as $eventId) {
                $chosen = $selectedDivisions[$eventId] ?? null;
                $divisionIds = $chosen ? array_filter((array) $chosen) : [];

                if (empty($divisionIds)) {
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

            $totalEvents = $enrolment->activeEvents()->count();
            $enrolment->forceFill([
                'fee_calculated' => $this->calculateFee($competition, $totalEvents, $enrolment->is_late),
                'is_late'        => $isLate,
            ])->save();

            // Notify the user responsible for this profile
            $notifiable = $competitor->notifiableUser();
            if ($notifiable) {
                $notifiable->notify(new EnrolmentConfirmedNotification($enrolment));
            }

            return $enrolment;
        });
    }

    /**
     * Link two EnrolmentEvents as Yakusuko partners.
     */
    public function resolveYakusukoPartner(EnrolmentEvent $ee, EnrolmentEvent $partnerEe): void
    {
        $ee->update(['partner_enrolment_event_id' => $partnerEe->id]);
        $partnerEe->update(['partner_enrolment_event_id' => $ee->id]);

        $ee->update(['yakusuko_complete' => true]);
        $partnerEe->update(['yakusuko_complete' => true]);

        $ee->load(['enrolment.competitor', 'competitionEvent.competition']);
        $partnerEe->load(['enrolment.competitor', 'competitionEvent']);

        $competition = $ee->competitionEvent->competition;
        $event       = $ee->competitionEvent;

        $eeUser      = $ee->enrolment->competitor?->notifiableUser();
        $partnerUser = $partnerEe->enrolment->competitor?->notifiableUser();

        if ($eeUser && $partnerUser) {
            $eeUser->notify(new YakusukoPartnerEnrolledNotification($competition, $event, $partnerUser));
            $partnerUser->notify(new YakusukoPartnerEnrolledNotification($competition, $event, $eeUser));
        }
    }

    /**
     * Remove a competitor from a specific event (soft-remove with reason).
     */
    public function removeParticipant(EnrolmentEvent $ee, \App\Models\User $removedBy, string $reason): void
    {
        DB::transaction(function () use ($ee, $removedBy, $reason) {
            $ee->forceFill([
                'removed'        => true,
                'removed_at'     => now(),
                'removed_by'     => $removedBy->id,
                'removal_reason' => $reason,
            ])->save();

            $enrolment = $ee->enrolment;
            $totalEvents = $enrolment->activeEvents()->count();
            $enrolment->forceFill([
                'fee_calculated' => $this->calculateFee(
                    $enrolment->competition,
                    $totalEvents,
                    $enrolment->is_late
                ),
            ])->save();
        });
    }

    /**
     * Re-add a previously removed competitor to an event.
     */
    public function readdParticipant(EnrolmentEvent $ee): void
    {
        DB::transaction(function () use ($ee) {
            $ee->forceFill([
                'removed'        => false,
                'removed_at'     => null,
                'removed_by'     => null,
                'removal_reason' => null,
            ])->save();

            $enrolment = $ee->enrolment;
            $totalEvents = $enrolment->activeEvents()->count();
            $enrolment->forceFill([
                'fee_calculated' => $this->calculateFee(
                    $enrolment->competition,
                    $totalEvents,
                    $enrolment->is_late
                ),
            ])->save();
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
