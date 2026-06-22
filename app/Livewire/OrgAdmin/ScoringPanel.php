<?php

namespace App\Livewire\OrgAdmin;

use App\Filament\OrgAdmin\Pages\Scoring as ScoringPage;
use App\Livewire\OrgAdmin\Concerns\HasDivisionScoring;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\MatchPenalty;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use App\Models\ScoreEvent;
use App\Notifications\Notification;
use App\Services\ScoringService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ScoringPanel extends Component
{
    use HasDivisionScoring;

    #[Locked]
    public int $division_id = 0;

    #[Locked]
    public ?int $competition_id = null;

    public bool  $rollcallMode              = false;
    public bool  $rollcallRequired          = true;
    public array $completedRollcallDivisions = [];

    public bool   $penaltyModalOpen           = false;
    #[Locked]
    public ?int   $penaltyModalResultId       = null;
    #[Locked]
    public ?int   $penaltyModalMatchId        = null;
    #[Locked]
    public string $penaltyModalType           = '';
    public array  $penaltyModalReasons        = [];
    public string $penaltyModalSelectedReason = '';
    public string $penaltyModalFreeText       = '';

    public function mount(int $divisionId, ?int $competitionId = null): void
    {
        $this->division_id    = $divisionId;
        $this->competition_id = $competitionId;

        $this->completedRollcallDivisions = $this->loadCompletedRollcallDivisionsFromDb();

        $division               = $this->selectedDivision;
        $this->rollcallRequired = (bool) ($division?->competitionEvent?->rollcall_required ?? true);

        $ees   = EnrolmentEvent::where('division_id', $this->division_id)->get(['id', 'removed']);
        $eeIds = $ees->pluck('id');

        if ($division?->status === 'complete') {
            $this->rollcallMode = false;
            return;
        }

        $hasAbsent = $ees->contains('removed', true);
        $hasScores = RoundRobinMatch::where('division_id', $this->division_id)->exists()
            || Result::whereIn('enrolment_event_id', $eeIds)
                ->where(fn ($q) => $q->whereNotNull('total_score')->orWhereNotNull('win_loss'))
                ->exists();

        $skipGate = ! $this->rollcallRequired && ($division?->competitionEvent?->isTournament() ?? false);

        if ($hasAbsent || $hasScores || $division?->status === 'running' || $skipGate) {
            $this->rollcallMode = false;
            if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
                $this->completedRollcallDivisions[] = $this->division_id;
            }
            if (empty($division->category_config)) {
                $this->snapshotCategories($division);
            }
        } else {
            $this->rollcallMode = true;
        }
    }

    public function placeholder()
    {
        return <<<'HTML'
        <div class="flex items-center justify-center py-16 text-gray-400 dark:text-gray-500">
            <svg class="animate-spin h-6 w-6 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span class="text-sm">Loading scoring panel…</span>
        </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.org-admin.scoring-panel', ['div' => $this->selectedDivision]);
    }

    public function closeSelf(): void
    {
        $this->dispatch('scoring-panel-closed');
    }

    // ─── Child re-render triggers ────────────────────────────────────────────

    #[On('bracket-match-recorded')]
    public function onBracketMatchRecorded(): void {}

    #[On('scores-saved')]
    public function onScoresSaved(int $divisionId): void {}

    // ─── Rollcall events ─────────────────────────────────────────────────────

    #[On('rollcall-completed')]
    public function onRollcallCompleted(int $divisionId): void
    {
        if ($divisionId !== $this->division_id) return;

        $this->rollcallMode = false;
        if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
            $this->completedRollcallDivisions[] = $this->division_id;
        }

        $division = Division::with('competitionEvent')->find($this->division_id);
        if ($division && empty($division->category_config)) {
            $this->snapshotCategories($division);
        }
        unset($this->selectedDivision);
    }

    #[On('cancel-scoring-requested')]
    public function onCancelScoringRequested(): void
    {
        $this->cancelScoring();
    }

    // ─── Division completion ──────────────────────────────────────────────────

    public function markDivisionComplete(): void
    {
        if (! $this->division_id) return;

        if ($this->isTournament()) {
            $hasBracket = RoundRobinMatch::where('division_id', $this->division_id)->exists();
            if (! $hasBracket) {
                Notification::make()->warning()->title('Cannot complete — bracket has not been generated yet.')->send();
                return;
            }
            $pending = RoundRobinMatch::where('division_id', $this->division_id)
                ->whereNotNull('away_enrolment_event_id')
                ->whereNull('home_result')
                ->count();
            if ($pending > 0) {
                Notification::make()->warning()->title("Cannot complete — {$pending} bracket match(es) still pending.")->send();
                return;
            }
        } else {
            $method = $this->getScoringMethod();
            $eeIds  = EnrolmentEvent::where('division_id', $this->division_id)->where('removed', false)->pluck('id');
            $results = Result::whereIn('enrolment_event_id', $eeIds)->orderBy('id')->get()->unique('enrolment_event_id');

            if (in_array($method, ['judges_total', 'judges_average'])) {
                $missing = $results->filter(fn ($r) => ! $r->disqualified && ! $r->forfeited && $r->total_score === null)->count();
                if ($missing > 0) {
                    Notification::make()->warning()->title("Cannot complete — {$missing} competitor(s) have no score entered.")->send();
                    return;
                }
            } elseif ($method === 'win_loss') {
                $missing = $results->filter(fn ($r) => ! $r->disqualified && ! $r->forfeited && $r->win_loss === null)->count();
                if ($missing > 0) {
                    Notification::make()->warning()->title("Cannot complete — {$missing} competitor(s) have no result recorded.")->send();
                    return;
                }
            } elseif (in_array($method, ['first_to_n', 'timed_points'])) {
                $missing = $results->filter(fn ($r) => ! $r->disqualified && ! $r->forfeited && $r->total_score === null)->count();
                if ($missing > 0) {
                    Notification::make()->warning()->title("Cannot complete — {$missing} competitor(s) have no points recorded.")->send();
                    return;
                }
            }
        }

        Division::find($this->division_id)?->update([
            'status'            => 'complete',
            'completed_at'      => now(),
            'completed_by'      => auth()->id(),
            'actual_end_at'     => now(),
            'scoring_locked_by' => null,
            'scoring_locked_at' => null,
        ]);

        Notification::make()->title('Division marked complete.')->success()->send();
        $this->redirect(
            ScoringPage::getUrl(array_filter([
                'competition_id'     => $this->competition_id,
                'competition_day_id' => $this->divisionDayId(),
                'highlight_division' => $this->division_id,
            ])),
            navigate: true
        );
    }

    public function reactivateDivision(): void
    {
        if (! $this->division_id) return;

        Division::find($this->division_id)?->update([
            'status'       => 'assigned',
            'completed_at' => null,
            'completed_by' => null,
        ]);

        if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
            $this->completedRollcallDivisions[] = $this->division_id;
        }

        $this->dispatch('division-reactivated', divisionId: $this->division_id);

        Notification::make()->title('Division re-activated — scoring is now editable.')->warning()->send();
        unset($this->selectedDivision);
    }

    public function cancelScoring(): void
    {
        if (! $this->division_id) return;

        RoundRobinMatch::where('division_id', $this->division_id)->delete();
        EnrolmentEvent::where('division_id', $this->division_id)->update(['removed' => false]);

        Division::find($this->division_id)?->update([
            'placement_override_mode' => false,
            'awarded_places'          => null,
            'status'                  => 'assigned',
            'scoring_locked_by'       => null,
            'scoring_locked_at'       => null,
            'category_config'         => null,
            'actual_start_at'         => null,
            'actual_end_at'           => null,
        ]);

        $eeIds     = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        $resultIds = Result::whereIn('enrolment_event_id', $eeIds)->pluck('id');

        JudgeScore::whereIn('result_id', $resultIds)->delete();
        ScoreEvent::whereIn('result_id', $resultIds)->delete();
        MatchPenalty::whereIn('result_id', $resultIds)->delete();
        Result::whereIn('id', $resultIds)->update([
            'total_score'          => null,
            'tiebreaker_score'     => null,
            'placement'            => null,
            'placement_overridden' => false,
            'win_loss'             => null,
            'disqualified'         => false,
            'forfeited'            => false,
        ]);

        $this->completedRollcallDivisions = array_values(array_diff($this->completedRollcallDivisions, [$this->division_id]));
        $this->dispatch('scoring-cleared');

        $this->redirect(
            ScoringPage::getUrl(array_filter([
                'competition_id'     => $this->competition_id,
                'competition_day_id' => $this->divisionDayId(),
                'highlight_division' => $this->division_id,
            ])),
            navigate: true
        );
    }

    public function returnToRollcall(): void
    {
        if (! $this->division_id) return;

        $this->completedRollcallDivisions = array_values(array_diff($this->completedRollcallDivisions, [$this->division_id]));
        RoundRobinMatch::where('division_id', $this->division_id)->delete();

        Division::find($this->division_id)?->update([
            'placement_override_mode' => false,
            'awarded_places'          => null,
            'status'                  => 'assigned',
            'category_config'         => null,
        ]);

        $eeIds     = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        $resultIds = Result::whereIn('enrolment_event_id', $eeIds)->pluck('id');
        JudgeScore::whereIn('result_id', $resultIds)->delete();
        ScoreEvent::whereIn('result_id', $resultIds)->delete();
        Result::whereIn('id', $resultIds)->update([
            'total_score'          => null,
            'tiebreaker_score'     => null,
            'placement'            => null,
            'placement_overridden' => false,
            'win_loss'             => null,
            'disqualified'         => false,
        ]);
        EnrolmentEvent::where('division_id', $this->division_id)->update(['removed' => false]);

        $this->rollcallMode = true;
        $this->dispatch('scoring-cleared');
        unset($this->selectedDivision);
    }

    public function isScoringComplete(): bool
    {
        if ($this->rollcallMode) return false;
        if (! $this->division_id) return false;

        if ($this->isTournament()) {
            $hasBracket = RoundRobinMatch::where('division_id', $this->division_id)->exists();
            if (! $hasBracket) return false;
            $pending = RoundRobinMatch::where('division_id', $this->division_id)
                ->whereNotNull('away_enrolment_event_id')
                ->whereNull('home_result')
                ->count();
            return $pending === 0
                && RoundRobinMatch::where('division_id', $this->division_id)
                    ->whereNotNull('home_result')
                    ->exists();
        }

        $method   = $this->getScoringMethod();
        $division = $this->selectedDivision;
        $dayId    = $division?->competition_day_id;

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
            ->pluck('id');

        $results = Result::whereIn('enrolment_event_id', $eeIds)->orderBy('id')->get()->unique('enrolment_event_id');

        if ($results->isEmpty()) return false;

        return $results->every(fn ($r) => $r->disqualified || $r->forfeited || match ($method) {
            'judges_total', 'judges_average' => $r->total_score !== null,
            'win_loss'                       => $r->win_loss !== null,
            'first_to_n', 'timed_points'    => $r->total_score !== null,
            default                          => true,
        });
    }

    // ─── Note modal ──────────────────────────────────────────────────────────

    public function saveNote(int $resultId, ?string $note = ''): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;
        $result->update(['note' => $note ?: null]);
        $this->dispatch('close-modal', id: 'note-modal');
    }

    // ─── Penalty modal ───────────────────────────────────────────────────────

    #[On('open-penalty-modal')]
    public function onOpenPenaltyModal(int $resultId, string $type, ?int $matchId = null): void
    {
        $div = $this->selectedDivision;
        if (! $div) return;
        if (! $this->findResult($resultId)) return;

        $this->penaltyModalResultId       = $resultId;
        $this->penaltyModalMatchId        = $matchId;
        $this->penaltyModalType           = $type;
        $this->penaltyModalSelectedReason = '';
        $this->penaltyModalFreeText       = '';

        if (in_array($type, ['warn', 'dq', 'forfeit'])) {
            $reasons = $div->competitionEvent->penaltyReasonsFor($type);
            if (empty($reasons) && $type === 'warn') {
                $this->applyPenalty($resultId, $type, null, $matchId);
                return;
            }
            $this->penaltyModalReasons = $reasons;
            $this->penaltyModalOpen    = true;
            $this->dispatch('open-modal', id: 'penalty-reason-modal');
        } else {
            $this->applyPenalty($resultId, $type, null, $matchId);
        }
    }

    public function confirmPenalty(?string $reason = null): void
    {
        if (! $this->penaltyModalResultId || ! $this->penaltyModalType) return;

        $this->applyPenalty(
            $this->penaltyModalResultId,
            $this->penaltyModalType,
            $reason ?? ($this->penaltyModalFreeText ?: null),
            $this->penaltyModalMatchId,
        );

        $this->penaltyModalOpen           = false;
        $this->penaltyModalSelectedReason = '';
        $this->penaltyModalFreeText       = '';
    }

    private function applyPenalty(int $resultId, string $type, ?string $reason, ?int $matchId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        if ($type === 'forfeit' && ($result->disqualified || $result->forfeited)) {
            Notification::make()->warning()->title('Forfeit not applied — competitor is already DQ\'d or forfeited.')->send();
            return;
        }
        if ($type === 'dq' && $result->forfeited) {
            Notification::make()->warning()->title('DQ not applied — competitor is forfeited. Undo the forfeit first.')->send();
            return;
        }

        $match = $matchId ? RoundRobinMatch::find($matchId) : null;

        $opponentResult = null;
        if ($type === 'opponent_point' && $match) {
            $opponentEeId   = $match->home_enrolment_event_id === $result->enrolment_event_id
                ? $match->away_enrolment_event_id
                : $match->home_enrolment_event_id;
            $opponentResult = $opponentEeId ? Result::where('enrolment_event_id', $opponentEeId)->first() : null;
        }

        ['triggered_dq' => $triggeredDq] = app(ScoringService::class)->addPenalty(
            $result, $type, $reason, $match, $opponentResult
        );

        $label = match ($type) {
            'warn'           => 'Warning added.',
            'dq'             => 'DQ applied.',
            'forfeit'        => 'Forfeit applied.',
            'deduction'      => '-1 deduction applied.',
            'opponent_point' => '+1 awarded to opponent.',
            default          => 'Penalty applied.',
        };
        Notification::make()->title($label)->warning()->send();

        if ($triggeredDq) {
            $result->refresh();
            $this->dispatch('dq-applied', resultId: $resultId);
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function snapshotCategories(Division $division): void
    {
        $mode = $division->competitionEvent->score_category_mode ?? 'single';
        if ($mode === 'single') return;
        $categories = $division->competitionEvent->scoreCategories()->get();
        if ($categories->isNotEmpty()) {
            $division->update(['category_config' => $categories->map(fn ($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'weight'     => $c->weight,
                'sort_order' => $c->sort_order,
            ])->values()->all()]);
        }
    }

    private function loadCompletedRollcallDivisionsFromDb(): array
    {
        if (! $this->competition_id) return [];

        return Division::whereHas('competitionEvent', fn ($q) =>
            $q->where('competition_id', $this->competition_id)
        )
        ->where(fn ($q) => $q
            ->whereIn('status', ['running', 'complete'])
            ->orWhereHas('enrolmentEvents', fn ($q2) => $q2->where('removed', true))
            ->orWhereHas('enrolmentEvents.result', fn ($q2) =>
                $q2->where(fn ($q3) => $q3->whereNotNull('total_score')->orWhereNotNull('win_loss'))
            )
            ->orWhereHas('roundRobinMatches')
        )
        ->pluck('id')
        ->toArray();
    }
}
