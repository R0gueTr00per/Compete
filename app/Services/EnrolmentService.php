<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Models\EnrolmentEvent;
use App\Models\User;
use App\Notifications\CartInvoiceNotification;
use App\Notifications\EnrolmentConfirmedNotification;
use App\Notifications\YakusukoPartnerEnrolledNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
    public function enrol(CompetitorProfile $competitor, Competition $competition, array $competitionEventIds, array $selectedDivisions = [], array $entryDetails = [], bool $notify = true): Enrolment
    {
        return DB::transaction(function () use ($competitor, $competition, $competitionEventIds, $selectedDivisions, $entryDetails, $notify) {
            $isLate = $competition->isLateEnrolment();
            $ownAccount = $competitor->account;
            $isOfficial = $ownAccount && $competition->isOfficial($ownAccount);
            $fee = $this->calculateFee($competition, count($competitionEventIds), $isLate, $isOfficial);

            $fillableDetails = array_filter($entryDetails, fn ($v) => $v !== null && $v !== '');

            $enrolment = Enrolment::firstOrNew(
                ['competition_id' => $competition->id, 'competitor_profile_id' => $competitor->id]
            );

            if (! $enrolment->exists) {
                $enrolment->fill(array_merge(['enrolled_at' => now(), 'is_late' => $isLate, 'is_official_discount' => $isOfficial, 'status' => 'pending'], $fillableDetails));
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

            $totalEvents   = $enrolment->activeEvents()->count();
            $feeCalculated = $this->calculateFee($competition, $totalEvents, $enrolment->is_late, $isOfficial);

            $enrolment->forceFill([
                'fee_calculated'       => $feeCalculated,
                'is_late'              => $isLate,
                'is_official_discount' => $isOfficial,
            ])->save();

            // Ensure every enrolment belongs to a cart (find or create for this user+competition).
            if (! $enrolment->cart_id) {
                $ownerUserId = $competitor->owner_user_id;
                $cart = EnrolmentCart::firstOrCreate(
                    ['user_id' => $ownerUserId, 'competition_id' => $competition->id, 'status' => 'submitted'],
                    [
                        'submitted_at'                 => $enrolment->enrolled_at ?? now(),
                        'fee_first_rate'               => $competition->fee_first_event,
                        'fee_additional_rate'          => $competition->fee_additional_event,
                        'fee_official_first_rate'      => $competition->fee_official_first_event,
                        'fee_official_additional_rate' => $competition->fee_official_additional_event,
                        'late_surcharge_rate'          => $competition->late_surcharge,
                        'platform_fee_rate'            => app('tenant')?->platform_fee,
                    ]
                );
                $enrolment->forceFill(['cart_id' => $cart->id])->save();
                // Recalculate cart total including platform fee
                $platformRate  = (float) ($cart->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
                $activeEnrols  = $cart->enrolments()->whereNotIn('status', ['draft', 'withdrawn']);
                $cart->forceFill([
                    'total_amount' => $activeEnrols->sum('fee_calculated') + $activeEnrols->count() * $platformRate,
                ])->save();
            }

            if ($notify) {
                $notifiable = $competitor->notifiableUser();
                if ($notifiable) {
                    $notifiable->notify(new EnrolmentConfirmedNotification($enrolment));
                }
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
                    $enrolment->is_late,
                    $enrolment->is_official_discount,
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
                    $enrolment->is_late,
                    $enrolment->is_official_discount,
                ),
            ])->save();
        });
    }

    // ── Cart checkout methods ────────────────────────────────────────────────

    /**
     * Find the user's existing draft cart, or create one.
     * Carts are now global per-user — not scoped to a single competition.
     */
    public function createOrResumeCart(User $user): EnrolmentCart
    {
        return EnrolmentCart::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'draft'],
        );
    }

    /**
     * Save (or update) a draft enrolment for a profile within a cart.
     * Existing draft enrolment events are replaced with the new selection.
     */
    public function saveDraftEntry(EnrolmentCart $cart, Competition $competition, CompetitorProfile $profile, array $entryDetails, array $selectedEntries, array $yakusukoPartners = []): Enrolment
    {
        return DB::transaction(function () use ($cart, $competition, $profile, $entryDetails, $selectedEntries, $yakusukoPartners) {
            $isLate      = $competition->isLateEnrolment();
            $isOfficial  = $profile->account && $competition->isOfficial($profile->account);

            $divisionsByEvent = $this->parseDivisionEntries($selectedEntries);
            $eventCount = count($divisionsByEvent);
            $fee = $this->calculateFee($competition, $eventCount, $isLate, $isOfficial);

            $fillable = array_filter($entryDetails, fn ($v) => $v !== null && $v !== '');

            $enrolment = Enrolment::firstOrNew([
                'cart_id'               => $cart->id,
                'competition_id'        => $competition->id,
                'competitor_profile_id' => $profile->id,
            ]);

            $enrolment->fill(array_merge([
                'enrolled_at'          => now(),
                'is_late'              => $isLate,
                'is_official_discount' => $isOfficial,
                'status'               => 'draft',
            ], $fillable));
            $enrolment->forceFill(['fee_calculated' => $fee])->save();

            // Replace event records
            $enrolment->enrolmentEvents()->delete();
            foreach ($divisionsByEvent as $eventId => $divisionIds) {
                foreach ($divisionIds as $divisionId) {
                    $enrolment->enrolmentEvents()->create([
                        'competition_event_id' => $eventId,
                        'division_id'          => $divisionId,
                        'yakusuko_complete'    => false,
                        'removed'              => false,
                    ]);
                }
            }

            return $enrolment;
        });
    }

    /**
     * Transition all draft enrolments in a cart to pending, fire notifications, mark cart submitted.
     *
     * @return Collection<int, Enrolment>
     */
    public function submitCart(EnrolmentCart $cart): Collection
    {
        return DB::transaction(function () use ($cart) {
            // Calculate totals while enrolments are still draft
            $cartTotal = $this->calculateCartTotal($cart);

            $enrolments = $cart->draftEnrolments()->with('competitor')->get();

            foreach ($enrolments as $enrolment) {
                $enrolment->forceFill(['status' => 'pending'])->save();

                $notifiable = $enrolment->competitor->notifiableUser();
                if ($notifiable) {
                    $notifiable->notify(new EnrolmentConfirmedNotification($enrolment));
                }
            }

            $cart->load('competition');
            $comp = $cart->competition;
            $cart->update([
                'status'                       => 'submitted',
                'submitted_at'                 => now(),
                'total_amount'                 => $cartTotal['grand_total'],
                'fee_first_rate'               => $comp?->fee_first_event,
                'fee_additional_rate'          => $comp?->fee_additional_event,
                'fee_official_first_rate'      => $comp?->fee_official_first_event,
                'fee_official_additional_rate' => $comp?->fee_official_additional_event,
                'late_surcharge_rate'          => $comp?->late_surcharge,
                'platform_fee_rate'            => app('tenant')?->platform_fee,
            ]);

            // Send consolidated invoice to the person who checked out
            $invoiceData = $this->buildInvoiceData($cart, $cartTotal);
            $cart->user->notify(new CartInvoiceNotification($cart, $invoiceData));

            return $enrolments;
        });
    }

    private function buildInvoiceData(EnrolmentCart $cart, array $cartTotal): array
    {
        // Use stored cart rate snapshots so the invoice always reflects what was agreed at checkout.
        return [
            'items'       => array_map(function ($item) use ($cart) {
                $isOfficial    = $item['is_official'];
                $useOfficialFees = $isOfficial
                    && $cart->fee_official_first_rate !== null
                    && $cart->fee_official_additional_rate !== null;
                $firstFee      = (float) ($useOfficialFees ? $cart->fee_official_first_rate : $cart->fee_first_rate);
                $additionalFee = (float) ($useOfficialFees ? $cart->fee_official_additional_rate : $cart->fee_additional_rate);
                $competition   = $item['competition'];

                $eventLines = $item['enrolment']->activeEvents
                    ->values()
                    ->map(function ($ee, $index) use ($firstFee, $additionalFee) {
                        return [
                            'event_name'     => $ee->competitionEvent->name,
                            'division_label' => $ee->division?->label ?? '',
                            'fee'            => $index === 0 ? $firstFee : $additionalFee,
                        ];
                    })
                    ->toArray();

                return [
                    'profile_name'     => $item['profile']->full_name,
                    'competition'      => $competition->name,
                    'competition_date' => $competition->competition_date->format('d M Y'),
                    'events'           => $eventLines,
                    'base_fee'         => $item['base_fee'],
                    'is_official'      => $isOfficial,
                    'late_surcharge'   => $item['late_surcharge'],
                    'platform_fee'     => $item['platform_fee'],
                    'subtotal'         => $item['subtotal'],
                ];
            }, $cartTotal['items']),
            'grand_total'  => $cartTotal['grand_total'],
        ];
    }

    /**
     * Calculate the full fee breakdown for all draft enrolments in a cart.
     *
     * @return array{items: array, platform_fee: float, grand_total: float}
     */
    public function calculateCartTotal(EnrolmentCart $cart): array
    {
        // Platform fee is org-level; competitions belong to the current tenant org.
        $org         = app('tenant');
        $platformFee = (float) ($org->platform_fee ?? 0);

        $items      = [];
        $grandTotal = 0.0;

        foreach ($cart->draftEnrolments()->with(['competitor', 'competition', 'activeEvents.competitionEvent', 'activeEvents.division'])->get() as $enrolment) {
            $competition   = $enrolment->competition;
            $entryFee      = (float) $enrolment->fee_calculated;
            $lateSurcharge = $enrolment->is_late ? (float) $competition->late_surcharge : null;
            $baseFee       = $entryFee - ($lateSurcharge ?? 0.0);
            $subtotal      = $entryFee + $platformFee;
            $grandTotal   += $subtotal;

            $items[] = [
                'enrolment'      => $enrolment,
                'profile'        => $enrolment->competitor,
                'competition'    => $competition,
                'base_fee'       => $baseFee,
                'is_official'    => (bool) $enrolment->is_official_discount,
                'late_surcharge' => $lateSurcharge,
                'platform_fee'   => $platformFee,
                'subtotal'       => $subtotal,
            ];
        }

        return [
            'items'       => $items,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Withdraw an enrolment. Checks cancellation cutoff. Flags refund if payment was received.
     */
    public function withdraw(Enrolment $enrolment, string $reason = ''): void
    {
        $competition  = $enrolment->competition;
        $org          = $competition->organisation;
        $cutoffDays   = (int) ($org->cancellation_days_before ?? 0);

        if (now()->isAfter($competition->competition_date->endOfDay())) {
            throw new RuntimeException('Withdrawal is not available after the competition date.');
        }

        if ($cutoffDays > 0 && now()->gte($competition->competition_date->subDays($cutoffDays))) {
            throw new RuntimeException("Withdrawal closed {$cutoffDays} days before the competition.");
        }

        DB::transaction(function () use ($enrolment, $reason) {
            $enrolment->forceFill([
                'status'            => 'withdrawn',
                'withdrawn_at'      => now(),
                'withdrawal_reason' => $reason ?: null,
                'refund_requested'  => $enrolment->payment_status === 'received',
            ])->save();
        });
    }

    /**
     * Edit the events on a confirmed/pending enrolment (post-checkout adjustment).
     * Removes deselected events, adds newly selected ones, recalculates fee.
     */
    public function editEnrolmentEvents(Enrolment $enrolment, array $selectedEntries): void
    {
        DB::transaction(function () use ($enrolment, $selectedEntries) {
            $competition      = $enrolment->competition;
            $newDivsByEvent   = $this->parseDivisionEntries($selectedEntries);
            $newEventIds      = array_keys($newDivsByEvent);

            $currentEventIds  = $enrolment->enrolmentEvents()
                ->where('removed', false)
                ->pluck('competition_event_id')
                ->unique()
                ->toArray();

            // Soft-remove events no longer selected
            foreach (array_diff($currentEventIds, $newEventIds) as $eventId) {
                $enrolment->enrolmentEvents()
                    ->where('competition_event_id', $eventId)
                    ->where('removed', false)
                    ->get()
                    ->each(fn ($ee) => $ee->forceFill([
                        'removed'        => true,
                        'removed_at'     => now(),
                        'removed_by'     => auth()->id(),
                        'removal_reason' => 'Removed by competitor',
                    ])->save());
            }

            // Add newly selected events
            foreach (array_diff($newEventIds, $currentEventIds) as $eventId) {
                foreach ($newDivsByEvent[$eventId] as $divisionId) {
                    $enrolment->enrolmentEvents()->create([
                        'competition_event_id' => $eventId,
                        'division_id'          => $divisionId,
                        'yakusuko_complete'    => false,
                        'removed'              => false,
                    ]);
                }
            }

            $totalEvents = $enrolment->enrolmentEvents()->where('removed', false)->count();
            $enrolment->forceFill([
                'fee_calculated' => $this->calculateFee(
                    $competition,
                    $totalEvents,
                    $enrolment->is_late,
                    $enrolment->is_official_discount,
                ),
            ])->save();
        });
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Parse 'd{division_id}' keys into [competition_event_id => [division_id, ...]] map.
     */
    private function parseDivisionEntries(array $selectedEntries): array
    {
        $map = [];
        foreach ($selectedEntries as $key) {
            $divisionId = (int) substr((string) $key, 1);
            $division   = Division::find($divisionId);
            if ($division) {
                $map[$division->competition_event_id][] = $divisionId;
            }
        }
        return $map;
    }

    public function calculateFee(Competition $competition, int $eventCount, bool $isLate, bool $isOfficial = false): float
    {
        if ($eventCount <= 0) {
            return 0.0;
        }

        $useOfficialFees = $isOfficial
            && $competition->fee_official_first_event !== null
            && $competition->fee_official_additional_event !== null;

        if ($useOfficialFees) {
            $fee = $competition->fee_official_first_event
                + ($eventCount - 1) * $competition->fee_official_additional_event;
        } else {
            $fee = $competition->fee_first_event
                + ($eventCount - 1) * $competition->fee_additional_event;
        }

        if ($isLate) {
            $fee += $competition->late_surcharge;
        }

        return (float) $fee;
    }
}
