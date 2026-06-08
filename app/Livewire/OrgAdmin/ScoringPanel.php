<?php

namespace App\Livewire\OrgAdmin;

use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\MatchPenalty;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use App\Models\ScoreEvent;
use App\Services\BracketService;
use App\Services\ScoringService;
use App\Notifications\Notification;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class ScoringPanel extends Component
{
    #[Locked]
    public int $division_id = 0;

    #[Locked]
    public ?int $competition_id = null;

    public array $judgeScores               = [];
    public array $categoryScores           = [];
    public array $tbPendingFlat             = [];
    public array $tbPendingCat              = [];
    public array $pointsInput               = [];
    public array $placementInput            = [];
    public array $rollcallPresent           = [];
    public array $bracketScoreInput         = [];
    public array $savedResultIds            = [];
    public array $completedRollcallDivisions = [];
    public bool  $rollcallMode              = false;
    public bool  $rollcallRequired          = true;
    public bool  $bracketExists             = false;
    public bool  $placementOverrideMode     = false;
    public bool  $confirmLowCompetitorCount = false;
    public bool  $manualPairingMode         = false;
    public array $manualPairings            = [];
    public array $pairingCompetitorList     = [];

    public array  $perfOrder    = [];

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

        $this->loadRollcallFromSession();
        $this->loadPerfOrderFromSession();
        $this->completedRollcallDivisions = $this->loadCompletedRollcallDivisionsFromDb();

        $division                    = $this->selectedDivision;
        $this->bracketExists         = RoundRobinMatch::where('division_id', $this->division_id)->exists();
        $this->placementOverrideMode = (bool) $division?->placement_override_mode;
        $this->rollcallRequired      = (bool) ($division?->competitionEvent?->rollcall_required ?? true);

        $ees   = EnrolmentEvent::where('division_id', $this->division_id)->get(['id', 'removed']);
        $eeIds = $ees->pluck('id');

        if ($division?->status === 'complete') {
            $this->rollcallMode   = false;
            $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
                ->where(fn ($q) => $q->whereNotNull('total_score')->orWhere('disqualified', true)->orWhere('forfeited', true))
                ->pluck('id')
                ->toArray();
            return;
        }

        $hasAbsent = $ees->contains('removed', true);
        $hasScores = $this->bracketExists
            || Result::whereIn('enrolment_event_id', $eeIds)
                ->where(fn ($q) => $q->whereNotNull('total_score')->orWhereNotNull('win_loss'))
                ->exists();

        $skipGate = ! $this->rollcallRequired && ($division?->competitionEvent?->isTournament() ?? false);

        if ($hasAbsent || $hasScores || $division?->status === 'running' || $skipGate) {
            $this->rollcallMode = false;
            if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
                $this->completedRollcallDivisions[] = $this->division_id;
            }
            $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
                ->where(fn ($q) => $q->whereNotNull('total_score')->orWhere('disqualified', true)->orWhere('forfeited', true))
                ->pluck('id')
                ->toArray();
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

    // ─── Rollcall ────────────────────────────────────────────────────────────

    public function toggleRollcallPresent(int $eeId): void
    {
        if (in_array($eeId, $this->rollcallPresent)) {
            $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, [$eeId]));
        } else {
            $this->rollcallPresent[] = $eeId;
        }
        $this->saveRollcallToSession();
    }

    public function markAllPresent(): void
    {
        $ids = EnrolmentEvent::where('division_id', $this->division_id)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->where('removed', false)
            ->pluck('id')
            ->toArray();

        $this->rollcallPresent = array_values(array_unique(array_merge($this->rollcallPresent, $ids)));
        $this->saveRollcallToSession();
    }

    public function unmarkAllPresent(): void
    {
        $ids = EnrolmentEvent::where('division_id', $this->division_id)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->where('removed', false)
            ->pluck('id')
            ->toArray();

        $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, $ids));
        $this->saveRollcallToSession();
    }

    public function toggleRollcall(?array $presentIds = null): void
    {
        if ($this->rollcallMode && $presentIds !== null) {
            $this->rollcallPresent = array_values(array_map('intval', $presentIds));
        }
        if ($this->rollcallMode) {
            $division = Division::with('competitionEvent')->find($this->division_id);
            $event    = $division?->competitionEvent;

            if ($this->rollcallRequired) {
                $activeEeIds = EnrolmentEvent::where('division_id', $this->division_id)
                    ->where('removed', false)
                    ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
                    ->pluck('id');

                $activePresent = collect($this->rollcallPresent)->intersect($activeEeIds);
                if ($activePresent->count() === 0) {
                    Notification::make()
                        ->title('0 competitor(s) marked present')
                        ->body('At least 2 competitors are needed to score. Click Begin Scoring again to proceed anyway.')
                        ->warning()
                        ->send();
                    return;
                }
                if ($activePresent->count() < 2 && ! $this->confirmLowCompetitorCount) {
                    $this->confirmLowCompetitorCount = true;
                    Notification::make()
                        ->title($activePresent->count() . ' competitor(s) marked present')
                        ->body('At least 2 competitors are needed to score. Click Begin Scoring again to proceed anyway.')
                        ->warning()
                        ->send();
                    return;
                }
                $this->confirmLowCompetitorCount = false;

                $absentIds = $activeEeIds->diff($this->rollcallPresent);
                if ($absentIds->isNotEmpty()) {
                    EnrolmentEvent::whereIn('id', $absentIds)->update(['removed' => true]);
                }

                $presentCount = $activePresent->count();
            } else {
                $presentCount = EnrolmentEvent::where('division_id', $this->division_id)
                    ->where('removed', false)
                    ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
                    ->count();
            }

            $awardedPlaces = match (true) {
                $presentCount <= 2  => $event?->awarded_places_2    ?? 2,
                $presentCount === 3 => $event?->awarded_places_3    ?? 3,
                default             => $event?->awarded_places_4plus ?? 3,
            };
            $statusUpdate = $event?->isTournament() ? [] : ['status' => 'running'];
            $division?->update(array_merge(['awarded_places' => $awardedPlaces], $statusUpdate));

            if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
                $this->completedRollcallDivisions[] = $this->division_id;
            }
            $this->rollcallMode = false;
            $division = Division::with('competitionEvent')->find($this->division_id);
            if ($division && empty($division->category_config)) {
                $this->snapshotCategories($division);
            }
            if (! $this->bracketExists) {
                $fmt = $division?->tournament_format ?? $division?->competitionEvent?->effectiveTournamentFormat();
                if (in_array($fmt, ['round_robin', 'single_elimination', 'double_elimination', 'repechage', 'se_3rd_place'])) {
                    $this->generateBracket();
                }
            }
        } else {
            $this->completedRollcallDivisions = array_values(array_diff($this->completedRollcallDivisions, [$this->division_id]));
            RoundRobinMatch::where('division_id', $this->division_id)->delete();

            Division::find($this->division_id)?->update(['placement_override_mode' => false, 'awarded_places' => null, 'status' => 'assigned', 'category_config' => null]);

            $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
            Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
                $result->judgeScores()->delete();
                $result->scoreEvents()->delete();
                $result->forceFill([
                    'total_score'          => null,
                    'tiebreaker_score'     => null,
                    'placement'            => null,
                    'placement_overridden' => false,
                    'win_loss'             => null,
                    'disqualified'         => false,
                ])->save();
            });

            EnrolmentEvent::where('division_id', $this->division_id)->update(['removed' => false]);

            $this->judgeScores              = [];
            $this->categoryScores           = [];
            $this->savedResultIds           = [];
            $this->pointsInput              = [];
            $this->placementInput           = [];
            $this->bracketScoreInput        = [];
            $this->bracketExists            = false;
            $this->rollcallMode             = true;
            $this->dispatch('scoring-cleared');
        }
    }

    public function removeNoShow(int $enrolmentEventId): void
    {
        $ee = EnrolmentEvent::find($enrolmentEventId);
        if (! $ee || $ee->division_id !== $this->division_id) return;

        $ee->forceFill(['removed' => true])->save();
        Notification::make()->title('Marked as absent.')->warning()->send();
    }

    public function undoRollcallRemoval(int $enrolmentEventId): void
    {
        $ee = EnrolmentEvent::find($enrolmentEventId);
        if (! $ee || $ee->division_id !== $this->division_id) return;

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
            $result->judgeScores()->delete();
            $result->forceFill([
                'total_score'          => null,
                'tiebreaker_score'     => null,
                'placement'            => null,
                'placement_overridden' => false,
                'win_loss'             => null,
                'disqualified'         => false,
            ])->save();
        });
        $this->judgeScores    = [];
        $this->savedResultIds = [];

        $ee->forceFill(['removed' => false])->save();
        Notification::make()->title('Competitor added.')->success()->send();
    }

    #[Computed]
    public function getRollcallRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $division = Division::with('competitionEvent')->find($this->division_id);
        $filter   = $division?->competitionEvent?->division_filter ?? '';

        $rows = EnrolmentEvent::where('division_id', $this->division_id)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with(['enrolment.competitor', 'enrolment.rank'])
            ->get()->toBase();

        [$active, $absent] = $rows->partition(fn ($ee) => ! $ee->removed);

        $map = fn ($ee, bool $isAbsent) => (object) [
            'ee_id'  => $ee->id,
            'name'   => $ee->enrolment->competitor?->full_name ?? '(unknown)',
            'info'   => $this->buildRollcallInfo($ee, $filter),
            'absent' => $isAbsent,
        ];

        return $active->map(fn ($ee) => $map($ee, false))->sortBy('name')
            ->merge($absent->map(fn ($ee) => $map($ee, true))->sortBy('name'));
    }

    // ─── Division / scoring queries ──────────────────────────────────────────

    #[Computed]
    public function selectedDivision(): ?Division
    {
        if (! $this->division_id) return null;
        return Division::with(['competitionEvent', 'completedBy.selfProfile'])->find($this->division_id);
    }

    #[Computed]
    public function competitorRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $division = $this->selectedDivision;
        $filter   = $division?->competitionEvent?->division_filter ?? '';

        $eeCollection = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with([
                'enrolment.competitor',
                'enrolment.rank',
                'result.judgeScores.judgeScoreDetails',
            ])
            ->get()->toBase();

        $missing = $eeCollection->filter(fn ($ee) => $ee->result === null);
        if ($missing->isNotEmpty()) {
            Result::insertOrIgnore($missing->map(fn ($ee) => [
                'enrolment_event_id' => $ee->id,
                'division_id'        => $ee->division_id,
                'created_at'         => now(),
                'updated_at'         => now(),
            ])->values()->all());
            $newResults = Result::whereIn('enrolment_event_id', $missing->pluck('id'))->get()->keyBy('enrolment_event_id');
            $missing->each(fn ($ee) => $ee->setRelation('result', $newResults->get($ee->id)));
        }

        return $eeCollection->map(function (EnrolmentEvent $ee) use ($division, $filter) {
                $result = $ee->result ?? app(ScoringService::class)->getOrCreateResult($ee);

                if (! isset($this->judgeScores[$result->id])) {
                    $scores = [];
                    foreach ($result->judgeScores->where('is_tiebreaker', false) as $js) {
                        $scores[$js->judge_number] = number_format((float) $js->score, 1);

                        if ($js->judgeScoreDetails->isNotEmpty() && ! isset($this->categoryScores[$result->id][$js->judge_number])) {
                            foreach ($js->judgeScoreDetails as $detail) {
                                $this->categoryScores[$result->id][$js->judge_number][$detail->score_category_id] =
                                    number_format((float) $detail->score, 1);
                            }
                        }
                    }

                    $eventCategories = $division?->competitionEvent?->scoreCategories;
                    if ($eventCategories && $eventCategories->isNotEmpty()) {
                        $prefill    = $division->competitionEvent->default_score ?? $division->competitionEvent->min_score;
                        $judgeCount = $division->competitionEvent->effectiveJudgeCount();
                        if ($prefill !== null) {
                            for ($i = 1; $i <= $judgeCount; $i++) {
                                foreach ($eventCategories as $cat) {
                                    if (! isset($this->categoryScores[$result->id][$i][$cat->id])) {
                                        $this->categoryScores[$result->id][$i][$cat->id] = number_format((float) $prefill, 1);
                                    }
                                }
                            }
                        }
                    } elseif (empty($scores)) {
                        // Flat mode: pre-fill judge inputs
                        $prefill = $division?->competitionEvent?->default_score ?? $division?->competitionEvent?->min_score;
                        if ($prefill !== null) {
                            $judgeCount = $division->competitionEvent->effectiveJudgeCount();
                            for ($i = 1; $i <= $judgeCount; $i++) {
                                $scores[$i] = number_format((float) $prefill, 1);
                            }
                        }
                    }

                    $this->judgeScores[$result->id] = $scores;
                }

                if (! isset($this->pointsInput[$result->id]) && $result->total_score !== null) {
                    $this->pointsInput[$result->id] = (int) $result->total_score;
                }

                if (! isset($this->placementInput[$result->id]) && $result->placement_overridden && $result->placement !== null) {
                    $this->placementInput[$result->id] = $result->placement;
                }

                return (object) [
                    'ee'     => $ee,
                    'result' => $result,
                    'name'   => $this->resolveEeName($ee),
                    'info'   => $this->buildRollcallInfo($ee, $filter),
                ];
            })
            ->pipe(function ($c) use ($division) {
                if ($division?->status === 'complete') {
                    return $c->sortBy(fn ($row) => [$row->result->placement ?? 999, $row->name]);
                }
                $sortMode = $division?->competitionEvent?->competitor_sort ?? 'first_name';
                if ($sortMode === 'random') {
                    if (! empty($this->perfOrder)) {
                        $index = array_flip($this->perfOrder);
                        return $c->sortBy(fn ($row) => $index[$row->ee->id] ?? PHP_INT_MAX);
                    }
                    $shuffled = $c->shuffle();
                    $this->perfOrder = $shuffled->pluck('ee.id')->values()->all();
                    session([$this->perfOrderSessionKey() => $this->perfOrder]);
                    return $shuffled;
                }
                return match ($sortMode) {
                    'surname'            => $c->sortBy(fn ($row) => strtolower($row->ee->enrolment->competitor?->surname ?? $row->name)),
                    'registration_order' => $c->sortBy(fn ($row) => $row->ee->enrolment->created_at),
                    default              => $c->sortBy(fn ($row) => strtolower($row->name)),
                };
            });
    }

    public function isTournament(): bool
    {
        return in_array($this->getTournamentFormat(), ['round_robin', 'single_elimination', 'double_elimination', 'repechage', 'se_3rd_place']);
    }

    public function isRoundRobin(): bool
    {
        return $this->getTournamentFormat() === 'round_robin';
    }

    public function isScoringComplete(): bool
    {
        if (! $this->division_id || $this->rollcallMode) return false;

        if ($this->isTournament()) {
            if (! $this->bracketExists) return false;
            $pending = RoundRobinMatch::where('division_id', $this->division_id)
                ->whereNotNull('away_enrolment_event_id')
                ->whereNull('home_result')
                ->count();
            return $pending === 0
                && RoundRobinMatch::where('division_id', $this->division_id)
                    ->whereNotNull('home_result')
                    ->exists();
        }

        $method = $this->getScoringMethod();
        $rows   = $this->competitorRows;

        if ($rows->isEmpty()) return false;

        if (in_array($method, ['judges_total', 'judges_average'])) {
            $allSaved = $rows->every(fn ($row) => $row->result->disqualified || $row->result->forfeited || in_array($row->result->id, $this->savedResultIds));
            if (! $allSaved) return false;
            if (! $rows->every(fn ($row) => $row->result->disqualified || $row->result->forfeited || $row->result->total_score !== null)) return false;
            $tiedGroups = $this->getTiedGroups();
            if ($tiedGroups->isNotEmpty()) {
                $allTiebreakersSaved = $tiedGroups->every(fn ($tg) => $tg->group->every(
                    fn ($row) => $row->result->tiebreaker_score !== null || $row->result->placement_overridden
                ));
                if (! $allTiebreakersSaved) return false;
            }
            $stillTied = $this->getStillTiedAfterTiebreaker();
            if ($stillTied->isNotEmpty()) {
                return $stillTied->every(fn ($group) => $group->every(fn ($r) => $r->result->placement_overridden));
            }
            return true;
        }

        return $rows->every(fn ($row) => $row->result->disqualified || $row->result->forfeited || match ($method) {
            'win_loss'                    => $row->result->win_loss !== null,
            'first_to_n', 'timed_points' => $row->result->total_score !== null,
            default                       => true,
        });
    }

    public function getTournamentFormat(): ?string
    {
        $div = $this->selectedDivision;
        return $div?->tournament_format ?? $div?->competitionEvent->effectiveTournamentFormat();
    }

    public function getScoringMethod(): ?string
    {
        $div = $this->selectedDivision;
        if (! $div) return null;
        return $div->scoring_method ?? $div->competitionEvent->effectiveScoringMethod();
    }

    public function getJudgeCount(): int
    {
        $div = $this->selectedDivision;
        if (! $div) return 3;
        return $div->competitionEvent->effectiveJudgeCount();
    }

    public function getScoreCategories(): \Illuminate\Support\Collection
    {
        $div = $this->selectedDivision;
        if (! $div) return collect();

        $mode = $div->competitionEvent->score_category_mode ?? 'single';
        if ($mode === 'single') return collect();

        if (! empty($div->category_config)) {
            return collect($div->category_config)->map(fn ($c) => (object) $c);
        }

        return $div->competitionEvent->scoreCategories()->get();
    }

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

    public function getTargetScore(): ?int
    {
        $div = $this->selectedDivision;
        if (! $div) return null;
        return $div->competitionEvent->effectiveTargetScore();
    }

    public function getRoundDuration(): ?int
    {
        $div = $this->selectedDivision;
        if (! $div) return null;
        return $div->competitionEvent->round_duration_seconds;
    }

    public function getTiebreakerDuration(): ?int
    {
        $div = $this->selectedDivision;
        if (! $div) return null;
        return $div->competitionEvent->tiebreak_duration_seconds;
    }

    public function getTiebreakerMode(): string
    {
        $div = $this->selectedDivision;
        if (! $div) return 'sudden_death';
        return $div->competitionEvent->getTiebreakerMode();
    }

    public function getOvertimeRounds(): int
    {
        $div = $this->selectedDivision;
        if (! $div) return 1;
        return $div->competitionEvent->getOvertimeRounds();
    }

    public function getIncrementButtons(): array
    {
        $div = $this->selectedDivision;
        if (! $div) return [1];
        return $div->competitionEvent->getIncrementButtons();
    }

    public function getAwardedPlacesLabel(): string
    {
        if (! $this->division_id) return '';
        $division = $this->selectedDivision;
        if (! $division) return '';

        $count = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->count();

        $event = $division->competitionEvent;
        $cap   = match (true) {
            $count <= 2  => $event->awarded_places_2    ?? 2,
            $count === 3 => $event->awarded_places_3    ?? 3,
            default      => $event->awarded_places_4plus ?? 3,
        };

        return match ($cap) {
            1       => '1st only',
            2       => '1st & 2nd',
            default => 'Podium',
        };
    }

    public function getScoringSettingPills(): array
    {
        $div = $this->selectedDivision;
        if (! $div) return [];

        $pills = [];

        $pills[] = match ($this->getTournamentFormat()) {
            'once_off'           => 'Single Perf',
            'single_elimination' => 'Single Elim',
            'double_elimination' => 'Double Elim',
            'round_robin'        => 'Round Robin',
            'se_3rd_place'       => 'SE 3rd Place',
            default              => $this->getTournamentFormat(),
        };

        $pills[] = match ($this->getScoringMethod()) {
            'judges_average' => 'Judges Avg',
            'judges_total'   => 'Judges Total',
            'win_loss'       => 'Win/Loss',
            'first_to_n'     => 'First to N',
            'timed_points'   => 'Timed Pts',
            default          => $this->getScoringMethod(),
        };

        if ($div->competitionEvent->high_low_drop) {
            $pills[] = 'Hi-Low Drop';
        }

        return $pills;
    }

    public function getEnabledPenaltyTypes(): array
    {
        $div = $this->selectedDivision;
        if (! $div) return [];
        $order   = ['warn', 'deduction', 'opponent_point', 'dq', 'forfeit'];
        $enabled = $div->competitionEvent->enabledPenaltyTypes();
        usort($enabled, fn ($a, $b) => array_search($a, $order) <=> array_search($b, $order));
        return $enabled;
    }

    public function hasPenalties(): bool
    {
        return ! empty($this->getEnabledPenaltyTypes());
    }

    public function getPenaltyLabel(string $type): string
    {
        return match ($type) {
            'warn'           => 'Warn',
            'dq'             => 'DQ',
            'forfeit'        => 'Forfeit',
            'deduction'      => '-1',
            'opponent_point' => '+1 Opp',
            default          => $type,
        };
    }

    public function getDqLabel(int $resultId): string
    {
        $result = $this->findResult($resultId);
        return $result?->forfeited ? 'Forfeit' : 'DQ';
    }

    #[Computed]
    public function allPenalties(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();
        $resultIds = Result::where('division_id', $this->division_id)->pluck('id');
        if ($resultIds->isEmpty()) return collect();
        return MatchPenalty::whereIn('result_id', $resultIds)->orderBy('created_at')->get();
    }

    public function getWarnCount(int $resultId, ?int $matchId = null): int
    {
        return $this->allPenalties
            ->where('result_id', $resultId)
            ->where('type', 'warn')
            ->when($matchId, fn ($c) => $c->where('round_robin_match_id', $matchId))
            ->count();
    }

    public function getPenaltyLog(int $resultId, ?int $matchId = null): array
    {
        $penalties = $this->allPenalties
            ->where('result_id', $resultId)
            ->when($matchId, fn ($c) => $c->where('round_robin_match_id', $matchId));

        $warnCount = 0;
        $log       = [];
        foreach ($penalties as $penalty) {
            if ($penalty->type === 'warn') {
                $warnCount++;
                $ordinal = match ($warnCount) {
                    1 => '1st', 2 => '2nd', 3 => '3rd',
                    default => "{$warnCount}th",
                };
                $log[] = ['id' => $penalty->id, 'label' => "{$ordinal} warning" . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'dq') {
                $log[] = ['id' => $penalty->id, 'label' => 'DQ' . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'forfeit') {
                $log[] = ['id' => $penalty->id, 'label' => 'Forfeit' . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'deduction') {
                $log[] = ['id' => $penalty->id, 'label' => '-1 deduction'];
            } elseif ($penalty->type === 'opponent_point') {
                $log[] = ['id' => $penalty->id, 'label' => '+1 to opponent'];
            }
        }
        return $log;
    }

    public function hasUndoablePenalty(int $resultId, ?int $matchId = null): bool
    {
        return $this->allPenalties
            ->where('result_id', $resultId)
            ->when($matchId, fn ($c) => $c->where('round_robin_match_id', $matchId))
            ->first(fn ($p) =>
                $p->type !== 'dq' ||
                ($p->type === 'dq' && (is_null($p->reason) || ! str_starts_with($p->reason ?? '', 'Auto-DQ:')))
            ) !== null;
    }

    public function hasSavedScores(): bool
    {
        return ! empty($this->savedResultIds);
    }

    // ─── Scoring actions ─────────────────────────────────────────────────────

    public function saveJudgeScores(int $resultId, array $clientCatScores = []): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        $service       = app(ScoringService::class);
        $event         = $result->enrolmentEvent->competitionEvent;
        $mode          = $event->score_category_mode ?? 'single';
        $hasCategories = $mode !== 'single' && $event->scoreCategories()->exists();

        if ($hasCategories) {
            // Merge DOM-collected values (fixes wire:model.blur + +/− button race condition)
            foreach ($clientCatScores as $judgeNum => $cats) {
                foreach ((array) $cats as $catId => $value) {
                    $this->categoryScores[$resultId][$judgeNum][$catId] = $value;
                }
            }
            foreach ($this->categoryScores[$resultId] ?? [] as $judgeNum => $catScores) {
                $filled = array_filter($catScores, fn ($v) => $v !== null && $v !== '');
                if (! empty($filled)) {
                    $service->submitCategoryJudgeScore($result, (int) $judgeNum, array_map('floatval', $filled));
                }
            }
        } else {
            foreach ($this->judgeScores[$resultId] ?? [] as $judgeNum => $score) {
                if ($score !== null && $score !== '') {
                    $service->submitJudgeScore($result, (int) $judgeNum, (float) $score);
                }
            }
        }

        if (! in_array($resultId, $this->savedResultIds)) {
            $this->savedResultIds[] = $resultId;
        }

        Notification::make()->title('Scores saved.')->success()->send();
    }

    public function undoJudgeScores(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        $service = app(ScoringService::class);

        // Clear tiebreaker scores first
        if ($result->tiebreaker_score !== null) {
            $service->clearTiebreakerScore($result);
        }

        // Clear all regular judge scores and total from DB
        $result->judgeScores()->where('is_tiebreaker', false)->each(function ($js) {
            $js->judgeScoreDetails()->delete();
            $js->delete();
        });
        $result->update(['total_score' => null]);
        $result->forceFill(['placement' => null, 'placement_overridden' => false])->save();

        // Clear DQ/Forfeit and penalty records
        MatchPenalty::where('result_id', $resultId)->delete();
        $result->disqualified = false;
        $result->forfeited    = false;
        $result->save();

        // Re-rank remaining scored competitors
        $service->autoRankDivision(Division::with('competitionEvent')->find($result->division_id));

        // Remove from saved set (inputs preserved so user sees old values to re-edit)
        unset($this->placementInput[$resultId]);
        $this->savedResultIds = array_values(array_diff($this->savedResultIds, [$resultId]));
    }

    public function saveWinLoss(int $resultId, string $value): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;
        app(ScoringService::class)->recordWinLoss($result, $value);
        Notification::make()->title('Result recorded.')->success()->send();
    }

    public function addPoints(int $resultId, float $amount): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        $target = $this->getTargetScore();
        if ($target !== null && (($result->total_score ?? 0) + $amount) > $target) return;

        app(ScoringService::class)->addPoints($result, $amount);
    }

    public function undoPoints(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;
        app(ScoringService::class)->undoLastPoints($result);
    }

    public function savePoints(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;
        app(ScoringService::class)->recordPoints($result, (int) ($this->pointsInput[$resultId] ?? 0));
        Notification::make()->title('Points saved.')->success()->send();
    }

    public function overridePlacement(int $resultId): void
    {
        $result    = $this->findResult($resultId);
        $placement = (int) ($this->placementInput[$resultId] ?? 0);
        if (! $result) return;

        if ($placement < 1) {
            if ($result->placement_overridden) {
                $result->forceFill(['placement' => null, 'placement_overridden' => false])->save();
                Notification::make()->title('Placement cleared.')->success()->send();
            }
            return;
        }

        $service = app(ScoringService::class);
        $service->overridePlacement($result, $placement);

        if ($result->fresh()->tiebreaker_score !== null) {
            $service->clearTiebreakerScore($result);
        }
    }

    public function headJudgeSavePlacement(int $resultId): void
    {
        $result    = $this->findResult($resultId);
        $placement = (int) ($this->placementInput[$resultId] ?? 0);

        if (! $result || $placement < 1) {
            Notification::make()->title('Select a place first.')->warning()->send();
            return;
        }

        $service = app(ScoringService::class);
        $service->overridePlacement($result, $placement);
        $service->autoRankDivision(Division::with('competitionEvent')->find($result->division_id));
        Notification::make()->title('Placement saved.')->success()->send();
    }

    public function headJudgeUndoPlacement(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;
        app(ScoringService::class)->clearPlacementOverride($result);
        unset($this->placementInput[$resultId]);
        Notification::make()->title('Placement cleared.')->success()->send();
    }

    public function clearOverride(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;
        app(ScoringService::class)->clearPlacementOverride($result);
        Notification::make()->title('Override cleared — auto-ranked.')->success()->send();
    }

    public function togglePlacementOverrideMode(): void
    {
        $this->placementOverrideMode = ! $this->placementOverrideMode;

        $division = Division::find($this->division_id);
        if (! $division) return;

        $division->placement_override_mode = $this->placementOverrideMode;
        $division->save();

        if ($this->placementOverrideMode) {
            Result::where('division_id', $division->id)
                ->where('placement_overridden', false)
                ->update(['placement' => null]);
        } else {
            Result::where('division_id', $division->id)
                ->update(['placement_overridden' => false, 'placement' => null]);

            $divisionResultIds = [];
            foreach ($this->competitorRows as $row) {
                $id = $row->result->id;
                unset($this->placementInput[$id]);
                $divisionResultIds[] = $id;
            }

            $this->savedResultIds = array_values(array_diff($this->savedResultIds, $divisionResultIds));

            app(ScoringService::class)->autoRankDivision($division);
            Notification::make()->title('Auto-ranking restored.')->success()->send();
        }
    }

    public function resetJudgeScores(): void
    {
        if (! $this->division_id) return;

        Division::find($this->division_id)?->update(['placement_override_mode' => false, 'category_config' => null]);

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
            $result->judgeScores()->delete();
            MatchPenalty::where('result_id', $result->id)->delete();
            $result->forceFill([
                'total_score'          => null,
                'tiebreaker_score'     => null,
                'placement'            => null,
                'placement_overridden' => false,
                'disqualified'         => false,
                'forfeited'            => false,
            ])->save();
        });

        $this->judgeScores    = [];
        $this->categoryScores = [];
        $this->savedResultIds = [];
        $this->placementOverrideMode    = false;
        Notification::make()->title('Scores cleared.')->success()->send();
    }

    public function toggleDisqualify(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        if (! $result->disqualified && $result->forfeited) {
            Notification::make()->warning()->title('Cannot DQ — competitor is forfeited. Undo the forfeit first.')->send();
            return;
        }

        app(ScoringService::class)->toggleDisqualify($result);
        $result->refresh();

        $label = $result->disqualified ? 'Disqualified.' : 'Disqualification removed.';
        Notification::make()->title($label)->warning()->send();

        $this->handleDqAutoAdvance($result);
        if ($this->isTournament()) {
            $this->applyBracketPlacements();
        }
    }

    public function saveTiebreakerScores(int $resultId, array $scores): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        $service       = app(ScoringService::class);
        $event         = $result->enrolmentEvent->competitionEvent;
        $mode          = $event->score_category_mode ?? 'single';
        $hasCategories = $mode !== 'single' && $event->scoreCategories()->exists();
        $method        = $this->getScoringMethod();
        $judgeTotals   = [];

        if ($hasCategories) {
            $categories = $this->getScoreCategories();
            foreach ($scores as $judgeNum => $data) {
                $cats = array_filter((array) $data, fn ($v) => $v !== null && $v !== '');
                if (empty($cats)) continue;
                $service->submitTiebreakerCategoryScore($result, (int) $judgeNum, $cats);
                $jTotal = 0.0;
                foreach ($categories as $cat) {
                    $v = (float) ($cats[$cat->id] ?? $cats[(string) $cat->id] ?? 0);
                    $jTotal += $mode === 'weighted' ? $v * ((float) $cat->weight / 100) : $v;
                }
                $judgeTotals[(int) $judgeNum] = $jTotal;
            }
        } else {
            foreach ($scores as $judgeNum => $val) {
                if ($val === null || $val === '') continue;
                $service->submitJudgeScore($result, (int) $judgeNum, (float) $val, true);
                $judgeTotals[(int) $judgeNum] = (float) $val;
            }
        }

        if (empty($judgeTotals)) {
            Notification::make()->title('Enter at least one judge score.')->warning()->send();
            return;
        }

        $values = collect($judgeTotals);
        $total  = $method === 'judges_average'
            ? round($values->avg(), 3)
            : round($values->sum(), 3);

        $service->saveTiebreakerScore($result, $total);
        Notification::make()->title('Tiebreaker score saved.')->success()->send();
    }

    public function clearTiebreakerScore(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        // Preserve scores so inputs re-populate after undo
        $result->loadMissing(['judgeScores' => fn ($q) => $q->where('is_tiebreaker', true), 'judgeScores.judgeScoreDetails']);
        foreach ($result->judgeScores->where('is_tiebreaker', true) as $js) {
            if ($js->judgeScoreDetails->isNotEmpty()) {
                foreach ($js->judgeScoreDetails as $detail) {
                    $this->tbPendingCat[$resultId][$js->judge_number][$detail->score_category_id] = number_format((float) $detail->score, 1);
                }
            } else {
                $this->tbPendingFlat[$resultId][$js->judge_number] = number_format((float) $js->score, 1);
            }
        }

        $eeIds   = EnrolmentEvent::where('division_id', $result->division_id)->pluck('id');
        $cleared = Result::whereIn('enrolment_event_id', $eeIds)
            ->where('total_score', $result->total_score)
            ->where('placement_overridden', true)
            ->pluck('id');

        if ($cleared->isNotEmpty()) {
            Result::whereIn('id', $cleared)->update(['placement_overridden' => false]);
            foreach ($cleared as $rid) {
                unset($this->placementInput[$rid]);
            }
        }

        app(ScoringService::class)->clearTiebreakerScore($result);
        Notification::make()->title('Tiebreaker score cleared.')->success()->send();
    }

    public function getTiedGroups(): \Illuminate\Support\Collection
    {
        $method = $this->getScoringMethod();
        if (! in_array($method, ['judges_total', 'judges_average'])) return collect();

        $rows     = $this->competitorRows;
        $allSaved = $rows->every(fn ($row) => $row->result->disqualified || in_array($row->result->id, $this->savedResultIds));
        if (! $allSaved) return collect();

        $scoreGroups = $rows
            ->filter(fn ($row) => $row->result->total_score !== null && ! $row->result->disqualified)
            ->groupBy(fn ($row) => (string) $row->result->total_score)
            ->sortByDesc(fn ($group, $key) => (float) $key);

        $cumulative = 0;
        $tiedGroups = collect();

        foreach ($scoreGroups as $group) {
            $startingPosition = $cumulative + 1;
            if ($group->count() > 1 && $startingPosition <= 3) {
                $tiedGroups->push((object) [
                    'group'             => $group,
                    'starting_position' => $startingPosition,
                ]);
            }
            $cumulative += $group->count();
        }

        return $tiedGroups->values();
    }

    public function getStillTiedAfterTiebreaker(): \Illuminate\Support\Collection
    {
        $method = $this->getScoringMethod();
        if (! in_array($method, ['judges_total', 'judges_average'])) return collect();

        $rows = $this->competitorRows;

        $allSaved = $rows->every(fn ($row) => $row->result->disqualified || in_array($row->result->id, $this->savedResultIds));
        if (! $allSaved) return collect();

        return $rows
            ->filter(fn ($row) => $row->result->tiebreaker_score !== null && ! $row->result->disqualified)
            ->groupBy(fn ($row) => (string) $row->result->total_score . '|' . (string) $row->result->tiebreaker_score)
            ->filter(fn ($group) => $group->count() > 1)
            ->values();
    }

    // ─── Penalty ─────────────────────────────────────────────────────────────

    public function openPenaltyModal(int $resultId, string $type, ?int $matchId = null): void
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

    public function undoPenalty(int $resultId, ?int $matchId = null): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        $match = $matchId ? RoundRobinMatch::find($matchId) : null;

        ['removed' => $removed, 'reversed_dq' => $reversedDq] = app(ScoringService::class)->undoLastPenalty($result, $match);

        if (! $removed) return;

        Notification::make()->title('Penalty undone.')->success()->send();

        if ($reversedDq) {
            $result->refresh();
            if ($this->isTournament()) {
                $this->applyBracketPlacements();
            }
        }
    }

    // ─── Bracket ─────────────────────────────────────────────────────────────

    public function generateBracket(): void
    {
        if (! $this->division_id) return;

        if (RoundRobinMatch::where('division_id', $this->division_id)->exists()) {
            Notification::make()->title('Bracket already generated.')->warning()->send();
            return;
        }

        $competitors = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with('enrolment.competitor', 'enrolment.rank')
            ->get()->toBase();

        $n = $competitors->count();
        if ($n < 2) {
            Notification::make()->title('Need at least 2 checked-in competitors.')->warning()->send();
            return;
        }

        $division = $this->selectedDivision;
        $event    = $division?->competitionEvent;

        if ($event?->manual_pairing) {
            $ordered = $this->buildBracketOrder($competitors, $event);
            $filter  = $event?->division_filter ?? '';
            $this->pairingCompetitorList = $ordered
                ->map(function ($ee) use ($filter) {
                    return ['ee_id' => $ee->id, 'name' => $this->resolveEeName($ee), 'info' => $this->buildRollcallInfo($ee, $filter)];
                })
                ->values()
                ->toArray();
            $this->manualPairings    = array_fill(0, (int) ceil($n / 2), ['home' => '', 'away' => '']);
            $this->manualPairingMode = true;
            return;
        }

        $ordered = $this->buildBracketOrder($competitors, $event);
        app(BracketService::class)->generate($division, $ordered);

        $this->bracketExists = true;

        $awardedPlaces = match (true) {
            $n <= 2  => $event?->awarded_places_2    ?? 2,
            $n === 3 => $event?->awarded_places_3    ?? 3,
            default  => $event?->awarded_places_4plus ?? 3,
        };
        $updateData = [
            'awarded_places'    => $awardedPlaces,
            'tournament_format' => $event?->effectiveTournamentFormat(),
            'scoring_method'    => $event?->effectiveScoringMethod(),
        ];
        if ($division?->status !== 'running') $updateData['status'] = 'running';
        $division?->update($updateData);
        unset($this->selectedDivision);

        Notification::make()->success()->title("Bracket generated for {$n} competitors.")->send();
    }

    public function closePairingWizard(): void
    {
        $this->manualPairingMode     = false;
        $this->manualPairings        = [];
        $this->pairingCompetitorList = [];
    }

    public function cancelPairing(): void
    {
        $this->closePairingWizard();

        if (! $this->bracketExists) {
            $this->js("window.dispatchEvent(new Event('pairing-cancelled'))");
        }
    }

    public function isPairingComplete(): bool
    {
        if (empty($this->manualPairings) || empty($this->pairingCompetitorList)) return false;

        $n           = count($this->pairingCompetitorList);
        $isOdd       = ($n % 2 !== 0);
        $byeCount    = 0;
        $assignedIds = [];

        foreach ($this->manualPairings as $pair) {
            $homeId  = isset($pair['home']) && $pair['home'] !== '' ? (int) $pair['home'] : null;
            $awayVal = $pair['away'] ?? '';
            $isBye   = $awayVal === 'bye';
            $awayId  = (!$isBye && $awayVal !== '') ? (int) $awayVal : null;

            if (! $homeId) return false;

            if ($isBye) {
                $byeCount++;
            } elseif (! $awayId) {
                return false;
            }

            $assignedIds[] = $homeId;
            if ($awayId) $assignedIds[] = $awayId;
        }

        if ($isOdd && $byeCount !== 1) return false;
        if (! $isOdd && $byeCount !== 0) return false;
        if (count($assignedIds) !== count(array_unique($assignedIds))) return false;

        return true;
    }

    public function confirmManualPairings(): void
    {
        if (! $this->division_id) return;

        if (RoundRobinMatch::where('division_id', $this->division_id)->exists()) {
            Notification::make()->title('Bracket already generated.')->warning()->send();
            $this->manualPairingMode = false;
            $this->bracketExists     = true;
            return;
        }

        $competitors = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with('enrolment.competitor')
            ->get()->toBase()
            ->keyBy('id');

        $n     = $competitors->count();
        $isOdd = ($n % 2 !== 0);

        if ($n < 2) {
            Notification::make()->title('Need at least 2 checked-in competitors.')->warning()->send();
            return;
        }

        $byeCount    = 0;
        $assignedIds = [];
        $errors      = [];

        foreach ($this->manualPairings as $i => $pair) {
            $homeId  = isset($pair['home']) && $pair['home'] !== '' ? (int) $pair['home'] : null;
            $awayVal = $pair['away'] ?? '';
            $isBye   = $awayVal === 'bye';
            $awayId  = (!$isBye && $awayVal !== '') ? (int) $awayVal : null;

            if (! $homeId) { $errors[] = 'Match ' . ($i + 1) . ': no home competitor selected.'; continue; }
            if (! $competitors->has($homeId)) { $errors[] = 'Match ' . ($i + 1) . ': competitor is no longer valid.'; continue; }

            if ($isBye) {
                $byeCount++;
            } elseif (! $awayId) {
                $errors[] = 'Match ' . ($i + 1) . ': no away competitor selected.';
            } elseif (! $competitors->has($awayId)) {
                $errors[] = 'Match ' . ($i + 1) . ': competitor is no longer valid.';
            }

            $assignedIds[] = $homeId;
            if ($awayId) $assignedIds[] = $awayId;
        }

        if (count($assignedIds) !== count(array_unique($assignedIds))) $errors[] = 'Each competitor may only appear once.';
        if ($isOdd && $byeCount !== 1) $errors[] = 'With an odd number of competitors, exactly one must receive a bye.';
        if (! $isOdd && $byeCount > 0) $errors[] = 'Byes are only allowed when the competitor count is odd.';
        if (count(array_unique($assignedIds)) < $n) $errors[] = 'Not all competitors have been assigned to a match.';

        if (! empty($errors)) {
            foreach ($errors as $msg) Notification::make()->title($msg)->warning()->send();
            return;
        }

        $ordered       = collect();
        $byeCompetitor = null;

        foreach ($this->manualPairings as $pair) {
            $homeId  = (int) $pair['home'];
            $awayVal = $pair['away'] ?? '';
            $isBye   = $awayVal === 'bye';
            $awayId  = $isBye ? null : (int) $awayVal;

            if ($isBye) {
                $byeCompetitor = $competitors->get($homeId);
            } else {
                $ordered->push($competitors->get($homeId));
                $ordered->push($competitors->get($awayId));
            }
        }

        if ($byeCompetitor) $ordered->push($byeCompetitor);

        $division = $this->selectedDivision;
        app(BracketService::class)->generate($division, $ordered);

        $this->bracketExists = true;

        $event         = $division?->competitionEvent;
        $awardedPlaces = match (true) {
            $n <= 2  => $event?->awarded_places_2    ?? 2,
            $n === 3 => $event?->awarded_places_3    ?? 3,
            default  => $event?->awarded_places_4plus ?? 3,
        };
        $updateData = [
            'awarded_places'    => $awardedPlaces,
            'tournament_format' => $event?->effectiveTournamentFormat(),
            'scoring_method'    => $event?->effectiveScoringMethod(),
        ];
        if ($division?->status !== 'running') $updateData['status'] = 'running';
        $division?->update($updateData);
        unset($this->selectedDivision);

        $this->manualPairingMode     = false;
        $this->manualPairings        = [];
        $this->pairingCompetitorList = [];

        Notification::make()->success()->title("Bracket generated for {$n} competitors.")->send();
    }

    public function recordBracketWinner(int $matchId, int $winnerEeId): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;

        $homeWins = $match->home_enrolment_event_id === $winnerEeId;
        $match->update(['home_result' => $homeWins ? 'win' : 'loss']);

        app(BracketService::class)->advance($match->fresh());
        $this->applyBracketPlacements();

        Notification::make()->success()->title('Result recorded.')->send();
    }

    public function recordBracketScore(int $matchId, ?float $directHome = null, ?float $directAway = null): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;
        if (! $match->isPending()) return;

        $homeScore = $directHome ?? (
            isset($this->bracketScoreInput[$matchId]['home']) && $this->bracketScoreInput[$matchId]['home'] !== ''
                ? (float) $this->bracketScoreInput[$matchId]['home']
                : null
        );
        $awayScore = $directAway ?? (
            isset($this->bracketScoreInput[$matchId]['away']) && $this->bracketScoreInput[$matchId]['away'] !== ''
                ? (float) $this->bracketScoreInput[$matchId]['away']
                : null
        );

        if ($homeScore === null || $awayScore === null) {
            Notification::make()->title('Enter scores for both competitors.')->warning()->send();
            return;
        }

        $scoringMethod = $this->getScoringMethod();
        if (in_array($scoringMethod, ['first_to_n', 'timed_points'])) {
            $target = $this->getTargetScore();
            if ($target !== null) {
                if ($homeScore > $target || $awayScore > $target) {
                    Notification::make()->title("Score cannot exceed {$target}.")->warning()->send();
                    return;
                }
                if ($homeScore == $target && $awayScore == $target) {
                    Notification::make()->title("Both competitors cannot have {$target} points.")->warning()->send();
                    return;
                }
            }
        }

        if ($homeScore === $awayScore) {
            if ($this->getTiebreakerMode() === 'overtime') {
                Notification::make()->title('Scores are tied — continue overtime or use head judge override.')->warning()->send();
                $this->dispatch('overtime-tied', matchId: $matchId);
            } else {
                Notification::make()->title('Scores are tied — use sudden death or head judge override.')->warning()->send();
                $this->dispatch('timer-tied', matchId: $matchId);
            }
            return;
        }

        $match->update(['home_score' => $homeScore, 'away_score' => $awayScore]);

        $homeResult = Result::where('enrolment_event_id', $match->home_enrolment_event_id)->first();
        $awayResult = Result::where('enrolment_event_id', $match->away_enrolment_event_id)->first();
        $homeDq     = ($homeResult?->disqualified || $homeResult?->forfeited) ?? false;
        $awayDq     = ($awayResult?->disqualified || $awayResult?->forfeited) ?? false;

        if ($homeDq && ! $awayDq) {
            $homeWins = false;
        } elseif ($awayDq && ! $homeDq) {
            $homeWins = true;
        } else {
            $homeWins = $homeScore > $awayScore;
        }

        $match->update(['home_result' => $homeWins ? 'win' : 'loss']);

        app(BracketService::class)->advance($match->fresh());
        $this->applyBracketPlacements();
        $this->dispatch('timer-reset', matchId: $matchId);
        $this->dispatch('bracket-saved');

        Notification::make()->success()->title('Score recorded.')->send();
    }

    public function onTimerExpired(int $matchId): void
    {
        if (! in_array($this->getScoringMethod(), ['first_to_n', 'timed_points'])) return;

        $homeScore = isset($this->bracketScoreInput[$matchId]['home']) && $this->bracketScoreInput[$matchId]['home'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['home'] : 0.0;
        $awayScore = isset($this->bracketScoreInput[$matchId]['away']) && $this->bracketScoreInput[$matchId]['away'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['away'] : 0.0;

        if ($homeScore === $awayScore) {
            $this->dispatch('timer-tied', matchId: $matchId);
        }
    }

    public function onOvertimeExpired(int $matchId): void
    {
        if (! in_array($this->getScoringMethod(), ['first_to_n', 'timed_points'])) return;

        $homeScore = isset($this->bracketScoreInput[$matchId]['home']) && $this->bracketScoreInput[$matchId]['home'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['home'] : 0.0;
        $awayScore = isset($this->bracketScoreInput[$matchId]['away']) && $this->bracketScoreInput[$matchId]['away'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['away'] : 0.0;

        if ($homeScore === $awayScore) {
            $this->dispatch('overtime-tied', matchId: $matchId);
        }
    }

    public function declareBracketWinner(int $matchId, string $side): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;
        if (! $match->isPending()) return;
        if (! in_array($this->getScoringMethod(), ['first_to_n', 'timed_points'])) return;
        if (! in_array($side, ['home', 'away'])) return;

        $homeWins = $side === 'home';
        $match->update([
            'home_score'  => $homeWins ? 1 : 0,
            'away_score'  => $homeWins ? 0 : 1,
            'home_result' => $homeWins ? 'win' : 'loss',
        ]);

        app(BracketService::class)->advance($match->fresh());
        $this->applyBracketPlacements();
        $this->dispatch('timer-reset', matchId: $matchId);

        Notification::make()->success()->title('Winner declared by head judge.')->send();
    }

    public function clearBracketResult(int $matchId): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;

        $format = $this->getTournamentFormat();
        $winner = $match->winnerId();
        $loser  = $match->loserId();

        if ($match->bracket === 'winners') {
            if ($format === 'repechage') {
                RoundRobinMatch::where('division_id', $this->division_id)
                    ->whereIn('bracket', ['repechage_a', 'repechage_b'])
                    ->delete();
            } elseif ($format === 'se_3rd_place') {
                $r1Count        = RoundRobinMatch::where('division_id', $this->division_id)->where('bracket', 'winners')->where('round', 1)->count();
                $maxWbRound     = $r1Count > 1 ? (int) ceil(log($r1Count, 2)) + 1 : 1;
                $semiFinalRound = $maxWbRound - 1;
                if ($match->round <= $semiFinalRound) {
                    RoundRobinMatch::where('division_id', $this->division_id)->where('bracket', 'repechage')->delete();
                }
            }

            if ($winner) {
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'winners', (int) ceil($match->bracket_slot / 2));

                if ($format === 'double_elimination' && $loser) {
                    [$lbRound, $lbSlot] = $match->round === 1
                        ? [1, (int) ceil($match->bracket_slot / 2)]
                        : [2 * ($match->round - 1), $match->bracket_slot];
                    $this->clearCompetitorFromSlot($loser, $lbRound, 'losers', $lbSlot);
                }

                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'grand_final')
                    ->where(fn ($q) => $q->where('home_enrolment_event_id', $winner)->orWhere('away_enrolment_event_id', $winner))
                    ->delete();
            }
        } elseif ($match->bracket === 'losers') {
            if ($winner) {
                $nextSlot = ($match->round % 2 === 1)
                    ? $match->bracket_slot
                    : (int) ceil($match->bracket_slot / 2);
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'losers', $nextSlot);

                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'grand_final')
                    ->where(fn ($q) => $q->where('home_enrolment_event_id', $winner)->orWhere('away_enrolment_event_id', $winner))
                    ->delete();
            }
        } elseif ($match->bracket === 'repechage') {
            if ($winner) {
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'repechage', (int) ceil($match->bracket_slot / 2));
            }
        }

        $eeIds     = array_filter([$match->home_enrolment_event_id, $match->away_enrolment_event_id]);
        $resultIds = Result::whereIn('enrolment_event_id', $eeIds)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->update(['disqualified' => false, 'forfeited' => false]);
        \App\Models\MatchPenalty::whereIn('result_id', $resultIds)->whereIn('type', ['dq', 'forfeit'])->delete();

        $match->update(['home_result' => null]);
        unset($this->bracketScoreInput[$matchId]);
        $this->applyBracketPlacements();
        $this->dispatch('timer-reset', matchId: $matchId);
        Notification::make()->success()->title('Result cleared.')->send();
    }

    public function resetBracket(): void
    {
        if (! $this->division_id) return;

        RoundRobinMatch::where('division_id', $this->division_id)->delete();
        $this->bracketExists = false;

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->where('disqualified', true)->update(['disqualified' => false, 'placement' => null]);
        Result::whereIn('enrolment_event_id', $eeIds)->where('forfeited', true)->update(['forfeited' => false]);

        Notification::make()->success()->title('Bracket cleared.')->send();
    }

    public function getBracketData(): array
    {
        $all = RoundRobinMatch::where('division_id', $this->division_id)
            ->with([
                'homeEnrolmentEvent.enrolment.competitor',
                'homeEnrolmentEvent.enrolment.rank',
                'awayEnrolmentEvent.enrolment.competitor',
                'awayEnrolmentEvent.enrolment.rank',
            ])
            ->orderBy('bracket')->orderBy('round')->orderBy('bracket_slot')
            ->get();

        $division = $this->selectedDivision;
        $filter   = $division?->competitionEvent?->division_filter ?? '';

        $eeNames = [];
        $eeInfo  = [];
        foreach ($all as $m) {
            foreach ([$m->homeEnrolmentEvent, $m->awayEnrolmentEvent] as $ee) {
                if ($ee && ! isset($eeNames[$ee->id])) {
                    $eeNames[$ee->id] = $this->resolveEeName($ee);
                    $eeInfo[$ee->id]  = $this->buildRollcallInfo($ee, $filter);
                }
            }
        }

        $eeIds         = $all->flatMap(fn ($m) => array_filter([$m->home_enrolment_event_id, $m->away_enrolment_event_id]))->unique()->values()->toArray();
        $resultIdsByEe = Result::whereIn('enrolment_event_id', $eeIds)->pluck('id', 'enrolment_event_id')->toArray();
        $matchIds      = $all->pluck('id')->toArray();
        $matchPenMap   = [];
        foreach (\App\Models\MatchPenalty::whereIn('round_robin_match_id', $matchIds)->whereIn('type', ['dq', 'forfeit'])->get() as $p) {
            $matchPenMap[$p->result_id][$p->round_robin_match_id][] = $p->type;
        }

        $scoredMatchesByEe = [];
        foreach ($all as $m) {
            if ($m->home_result !== null && ! $m->isBye()) {
                foreach ([$m->home_enrolment_event_id, $m->away_enrolment_event_id] as $eeId) {
                    if ($eeId) $scoredMatchesByEe[$eeId][] = $m;
                }
            }
        }

        $map = ['winners' => [], 'losers' => [], 'repechage' => [], 'repechage_a' => [], 'repechage_b' => [], 'grand_final' => []];
        foreach ($all as $m) {
            if ($m->isPending() && ! $m->isBye()) {
                if (! isset($this->bracketScoreInput[$m->id]['home'])) {
                    $this->bracketScoreInput[$m->id]['home'] = $m->home_score !== null
                        ? (string) ((float) $m->home_score + 0) : '0';
                }
                if (! isset($this->bracketScoreInput[$m->id]['away'])) {
                    $this->bracketScoreInput[$m->id]['away'] = $m->away_score !== null
                        ? (string) ((float) $m->away_score + 0) : '0';
                }
            }
            $winner    = $m->winnerId();
            $canUndo   = false;
            if ($m->home_result !== null && $winner) {
                $usedDownstream = false;
                foreach ($scoredMatchesByEe[$winner] ?? [] as $m2) {
                    if ($m2->id === $m->id) continue;
                    if ($m2->bracket === $m->bracket && $m2->round > $m->round) { $usedDownstream = true; break; }
                    if (in_array($m->bracket, ['winners', 'losers']) && $m2->bracket === 'grand_final') { $usedDownstream = true; break; }
                }
                $canUndo = ! $usedDownstream;
            }
            $homeResultId     = $resultIdsByEe[$m->home_enrolment_event_id] ?? null;
            $awayResultId     = $resultIdsByEe[$m->away_enrolment_event_id] ?? null;
            $homeTypesInMatch = $homeResultId ? ($matchPenMap[$homeResultId][$m->id] ?? []) : [];
            $awayTypesInMatch = $awayResultId ? ($matchPenMap[$awayResultId][$m->id] ?? []) : [];

            $map[$m->bracket][$m->round][] = (object) [
                'id'                    => $m->id,
                'slot'                  => $m->bracket_slot,
                'home_id'               => $m->home_enrolment_event_id,
                'away_id'               => $m->away_enrolment_event_id,
                'home_name'             => $eeNames[$m->home_enrolment_event_id] ?? '—',
                'home_info'             => $eeInfo[$m->home_enrolment_event_id] ?? '',
                'away_name'             => $m->away_enrolment_event_id
                    ? ($eeNames[$m->away_enrolment_event_id] ?? '—')
                    : ($m->home_result === null ? 'Waiting...' : 'BYE'),
                'away_info'             => $m->away_enrolment_event_id ? ($eeInfo[$m->away_enrolment_event_id] ?? '') : '',
                'home_result'           => $m->home_result,
                'home_score'            => $m->home_score,
                'away_score'            => $m->away_score,
                'is_bye'                => $m->isBye(),
                'is_pending'            => $m->isPending() && ! $m->isBye(),
                'winner_id'             => $winner,
                'loser_id'              => $m->loserId(),
                'can_undo'              => $canUndo,
                'home_dq_in_match'      => in_array('dq', $homeTypesInMatch),
                'home_forfeit_in_match' => in_array('forfeit', $homeTypesInMatch),
                'away_dq_in_match'      => in_array('dq', $awayTypesInMatch),
                'away_forfeit_in_match' => in_array('forfeit', $awayTypesInMatch),
            ];
        }

        return $map;
    }

    // ─── Division completion ──────────────────────────────────────────────────

    public function markDivisionComplete(): void
    {
        if (! $this->division_id) return;

        if ($this->isTournament()) {
            if (! $this->bracketExists) {
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
            if (in_array($method, ['judges_total', 'judges_average'])) {
                $missing = $this->competitorRows
                    ->filter(fn ($row) => ! $row->result->disqualified && ! $row->result->forfeited && $row->result->total_score === null)
                    ->count();
                if ($missing > 0) {
                    Notification::make()->warning()->title("Cannot complete — {$missing} competitor(s) have no score entered.")->send();
                    return;
                }
            } elseif ($method === 'win_loss') {
                $missing = $this->competitorRows
                    ->filter(fn ($row) => ! $row->result->disqualified && ! $row->result->forfeited && $row->result->win_loss === null)
                    ->count();
                if ($missing > 0) {
                    Notification::make()->warning()->title("Cannot complete — {$missing} competitor(s) have no result recorded.")->send();
                    return;
                }
            } elseif (in_array($method, ['first_to_n', 'timed_points'])) {
                $missing = $this->competitorRows
                    ->filter(fn ($row) => ! $row->result->disqualified && ! $row->result->forfeited && $row->result->total_score === null)
                    ->count();
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
            'scoring_locked_by' => null,
            'scoring_locked_at' => null,
        ]);
        Notification::make()->title('Division marked complete.')->success()->send();
        $this->dispatch('division-complete');
    }

    public function reactivateDivision(): void
    {
        if (! $this->division_id) return;

        Division::find($this->division_id)?->update([
            'status'       => 'assigned',
            'completed_at' => null,
            'completed_by' => null,
        ]);

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
            ->where(fn ($q) => $q->whereNotNull('total_score')->orWhere('disqualified', true)->orWhere('forfeited', true))
            ->pluck('id')
            ->toArray();

        if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
            $this->completedRollcallDivisions[] = $this->division_id;
        }

        Notification::make()->title('Division re-activated — scoring is now editable.')->warning()->send();
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
        ]);

        $this->perfOrder = [];
        session()->forget($this->perfOrderSessionKey());

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
        $cancelledEeIds                   = $eeIds->toArray();
        $this->rollcallPresent            = array_values(array_diff($this->rollcallPresent, $cancelledEeIds));
        $this->saveRollcallToSession();
        $this->dispatch('scoring-cleared');
        $this->dispatch('scoring-panel-closed');
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function rollcallSessionKey(): string
    {
        return 'scoring_rollcall_' . ($this->competition_id ?? 0);
    }

    private function saveRollcallToSession(): void
    {
        session([$this->rollcallSessionKey() => $this->rollcallPresent]);
    }

    private function loadRollcallFromSession(): void
    {
        $this->rollcallPresent = session($this->rollcallSessionKey(), []);
    }

    private function clearRollcallFromSession(): void
    {
        session()->forget($this->rollcallSessionKey());
    }

    private function perfOrderSessionKey(): string
    {
        return 'scoring_perf_order_' . ($this->division_id ?? 0);
    }

    private function loadPerfOrderFromSession(): void
    {
        $stored = session($this->perfOrderSessionKey());
        if (is_array($stored)) {
            $this->perfOrder = $stored;
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

    private function findResult(int $resultId): ?Result
    {
        $result = Result::find($resultId);
        if (! $result || ! $this->division_id) return null;
        if (! EnrolmentEvent::where('id', $result->enrolment_event_id)
            ->where('division_id', $this->division_id)
            ->exists()) {
            return null;
        }
        return $result;
    }

    private function resolveEeName(?EnrolmentEvent $ee): string
    {
        if (! $ee) return '—';
        return $ee->enrolment->competitor?->full_name ?? '—';
    }

    private function buildRollcallInfo(EnrolmentEvent $ee, string $filter): string
    {
        $parts = [];

        if (str_contains($filter, 'age')) {
            $age = $ee->enrolment->competitor?->age;
            if ($age !== null) $parts[] = $age . 'yo';
        }
        if (str_contains($filter, 'weight')) {
            $kg = $ee->enrolment->weight_kg;
            if ($kg) $parts[] = $kg . 'kg';
        }
        if (str_contains($filter, 'rank')) {
            $rank = $ee->enrolment->rank?->name;
            if ($rank) $parts[] = $rank;
        }
        if (str_contains($filter, 'sex')) {
            $gender = $ee->enrolment->competitor?->gender;
            if ($gender) $parts[] = match ($gender) {
                'M' => 'Male',
                'F' => 'Female',
                default => $gender,
            };
        }

        return $parts ? implode(', ', $parts) : '';
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
            $this->handleDqAutoAdvance($result);
            if ($this->isTournament()) {
                $this->applyBracketPlacements();
            } else {
                if (! in_array($result->id, $this->savedResultIds)) {
                    $this->savedResultIds[] = $result->id;
                }
            }
        }
    }

    private function handleDqAutoAdvance(Result $result): void
    {
        if (! $result->disqualified && ! $result->forfeited) return;
        if (! $this->isTournament()) return;
        if (! in_array($this->getScoringMethod(), ['first_to_n', 'timed_points', 'win_loss'])) return;

        $eeId  = $result->enrolment_event_id;
        $match = RoundRobinMatch::where('division_id', $this->division_id)
            ->whereNull('home_result')
            ->whereNotNull('away_enrolment_event_id')
            ->where(fn ($q) => $q->where('home_enrolment_event_id', $eeId)->orWhere('away_enrolment_event_id', $eeId))
            ->first();

        if ($match) {
            $winnerEeId  = $match->home_enrolment_event_id === $eeId
                ? $match->away_enrolment_event_id
                : $match->home_enrolment_event_id;
            $otherResult = $winnerEeId ? Result::where('enrolment_event_id', $winnerEeId)->first() : null;

            if ($winnerEeId && ! $otherResult?->disqualified && ! $otherResult?->forfeited) {
                $homeWins  = $match->home_enrolment_event_id === $winnerEeId;
                $homeScore = isset($this->bracketScoreInput[$match->id]['home'])
                    ? (float) $this->bracketScoreInput[$match->id]['home'] : null;
                $awayScore = isset($this->bracketScoreInput[$match->id]['away'])
                    ? (float) $this->bracketScoreInput[$match->id]['away'] : null;
                $match->update([
                    'home_result' => $homeWins ? 'win' : 'loss',
                    'home_score'  => $homeScore,
                    'away_score'  => $awayScore,
                ]);
                app(BracketService::class)->advance($match->fresh());
                $this->applyBracketPlacements();
                Notification::make()->title('Match awarded to opponent.')->info()->send();
            }
        }
    }

    private function applyBracketPlacements(): void
    {
        $service         = app(ScoringService::class);
        $division        = Division::with('competitionEvent')->find($this->division_id);
        $competitorCount = EnrolmentEvent::where('division_id', $this->division_id)->where('removed', false)->count();
        $event           = $division?->competitionEvent;
        $cap             = match (true) {
            $competitorCount <= 2  => $event?->awarded_places_2    ?? 2,
            $competitorCount === 3 => $event?->awarded_places_3    ?? 3,
            default               => $event?->awarded_places_4plus ?? 3,
        };
        $matches = RoundRobinMatch::where('division_id', $this->division_id)->whereNotNull('home_result')->get();

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->where('removed', false)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)
            ->where('placement_overridden', false)
            ->whereNotNull('placement')
            ->update(['placement' => null]);

        $format = $this->getTournamentFormat();

        if ($format === 'round_robin') {
            $allEeIds = EnrolmentEvent::where('division_id', $this->division_id)->where('removed', false)->pluck('id');
            if ($matches->isEmpty()) return;

            $winCounts = $allEeIds->mapWithKeys(fn ($id) => [$id => 0])->toArray();
            foreach ($matches as $m) {
                $winnerId = $m->winnerId();
                if ($winnerId && isset($winCounts[$winnerId])) $winCounts[$winnerId]++;
            }
            arsort($winCounts);

            $rank        = 1;
            $prevWins    = null;
            $countAtRank = 0;
            foreach ($winCounts as $eeId => $wins) {
                if ($prevWins !== null && $wins < $prevWins) { $rank += $countAtRank; $countAtRank = 0; }
                $this->setBracketPlacement((int) $eeId, $rank, $service, $cap);
                $prevWins = $wins;
                $countAtRank++;
            }
            return;
        } elseif ($format === 'se_3rd_place') {
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service, $cap);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service, $cap);
            }
            $repFinal = $matches->where('bracket', 'repechage')->sortByDesc('round')->first();
            if ($repFinal?->winnerId()) {
                $this->setBracketPlacement($repFinal->winnerId(), 3, $service, $cap);
                if ($repFinal->loserId()) $this->setBracketPlacement($repFinal->loserId(), 4, $service, $cap);
            } elseif ($wbFinalRound >= 2) {
                foreach ($matches->where('bracket', 'winners')->where('round', $wbFinalRound - 1) as $semi) {
                    if ($semi->loserId()) $this->setBracketPlacement($semi->loserId(), 3, $service, $cap);
                }
            }
            return;
        } elseif ($format === 'double_elimination') {
            $gf = $matches->firstWhere('bracket', 'grand_final');
            if ($gf?->winnerId()) {
                $this->setBracketPlacement($gf->winnerId(), 1, $service, $cap);
                if ($gf->loserId()) $this->setBracketPlacement($gf->loserId(), 2, $service, $cap);
            }
            $lbFinalRound = $matches->where('bracket', 'losers')->max('round');
            $lbFinal      = $matches->where('bracket', 'losers')->where('round', $lbFinalRound)->first();
            if ($lbFinal?->loserId()) $this->setBracketPlacement($lbFinal->loserId(), 3, $service, $cap);
        } elseif ($format === 'repechage') {
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service, $cap);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service, $cap);
            }
            foreach (['repechage_a', 'repechage_b'] as $repBracket) {
                $repMatches  = $matches->where('bracket', $repBracket);
                $maxRepRound = $repMatches->max('round');
                $repFinal    = $repMatches->where('round', $maxRepRound)->first();
                if ($repFinal?->winnerId()) $this->setBracketPlacement($repFinal->winnerId(), 3, $service, $cap);
            }
        } else {
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service, $cap);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service, $cap);
            }
            if ($wbFinalRound >= 2) {
                foreach ($matches->where('bracket', 'winners')->where('round', $wbFinalRound - 1) as $semi) {
                    if ($semi->loserId()) $this->setBracketPlacement($semi->loserId(), 3, $service, $cap);
                }
            }
        }
    }

    private function setBracketPlacement(int $eeId, int $placement, ScoringService $service, int $cap = 3): void
    {
        if ($placement > $cap) return;
        $ee = EnrolmentEvent::with('result')->find($eeId);
        if (! $ee) return;
        $result = $ee->result ?? $service->getOrCreateResult($ee);
        if (! $result->placement_overridden && ! $result->disqualified) {
            $result->forceFill(['placement' => $placement])->save();
        }
    }

    private function clearCompetitorFromSlot(int $eeId, int $round, string $bracket, int $slot): void
    {
        $match = RoundRobinMatch::where('division_id', $this->division_id)
            ->where('round', $round)->where('bracket', $bracket)->where('bracket_slot', $slot)
            ->first();

        if (! $match) return;

        if ($match->home_enrolment_event_id === $eeId) {
            if ($match->away_enrolment_event_id !== null) {
                $match->update([
                    'home_enrolment_event_id' => $match->away_enrolment_event_id,
                    'away_enrolment_event_id' => null,
                ]);
            } else {
                $match->delete();
            }
        } elseif ($match->away_enrolment_event_id === $eeId) {
            $match->update(['away_enrolment_event_id' => null]);
            $match->refresh();
            if ($match->home_enrolment_event_id === null) $match->delete();
        }
    }

    private function buildBracketOrder(\Illuminate\Support\Collection $competitors, ?\App\Models\CompetitionEvent $event): \Illuminate\Support\Collection
    {
        $sorted = match ($event?->bracket_sort ?? 'first_name') {
            'surname'            => $competitors->sortBy(fn ($ee) => strtolower($ee->enrolment->competitor?->surname ?? '')),
            'registration_order' => $competitors->sortBy(fn ($ee) => $ee->enrolment->created_at),
            'random'             => $competitors->shuffle(),
            default              => $competitors->sortBy(fn ($ee) => strtolower($ee->enrolment->competitor?->first_name ?? '')),
        };
        $sorted = $sorted->values();

        $sorted = match ($event?->bracket_first_round_order) {
            'seed_by_rank'         => $this->applyRankSeeding($sorted),
            'match_similar_age'    => $competitors->sortBy(fn ($ee) => $ee->enrolment->competitor?->age ?? 0)->values(),
            'match_similar_weight' => $competitors->sortBy(fn ($ee) => (float) ($ee->enrolment->weight_kg ?? 0))->values(),
            default                => $sorted,
        };

        if ($event?->bracket_prefer_different_dojo)     $sorted = $this->applyPreferDifferentDojo($sorted);
        if ($event?->bracket_avoid_repeat_matchups)     $sorted = $this->applyAvoidRepeatMatchups($sorted);

        return $sorted;
    }

    private function applyRankSeeding(\Illuminate\Support\Collection $competitors): \Illuminate\Support\Collection
    {
        $ranked = $competitors->sortByDesc(fn ($ee) => $ee->enrolment->rank?->sort_order ?? 0)->values();
        $result = [];
        $lo     = 0;
        $hi     = $ranked->count() - 1;
        while ($lo <= $hi) {
            $result[] = $ranked[$lo++];
            if ($lo <= $hi) $result[] = $ranked[$hi--];
        }
        return collect($result);
    }

    private function applyPreferDifferentDojo(\Illuminate\Support\Collection $competitors): \Illuminate\Support\Collection
    {
        $arr = $competitors->all();
        $n   = count($arr);
        for ($i = 0; $i < $n - 2; $i += 2) {
            $dojoA = $arr[$i]->enrolment->dojo_name ?? null;
            $dojoB = $arr[$i + 1]->enrolment->dojo_name ?? null;
            if ($dojoA && $dojoB && $dojoA === $dojoB) {
                [$arr[$i + 1], $arr[$i + 2]] = [$arr[$i + 2], $arr[$i + 1]];
            }
        }
        return collect($arr);
    }

    private function applyAvoidRepeatMatchups(\Illuminate\Support\Collection $competitors): \Illuminate\Support\Collection
    {
        if (! $this->competition_id || $competitors->isEmpty()) return $competitors;

        $profileById       = $competitors->keyBy('id')->map(fn ($ee) => $ee->enrolment->competitor_id);
        $profileIds        = $profileById->values()->unique();
        $currentDivisionId = $competitors->first()->division_id;

        $otherEeMap = EnrolmentEvent::with('enrolment')
            ->whereHas('enrolment', fn ($q) =>
                $q->where('competition_id', $this->competition_id)->whereIn('competitor_id', $profileIds)
            )
            ->where('division_id', '!=', $currentDivisionId)
            ->get()
            ->keyBy('id')
            ->map(fn ($ee) => $ee->enrolment->competitor_id);

        if ($otherEeMap->isEmpty()) return $competitors;

        $priorPairs = RoundRobinMatch::whereIn('home_enrolment_event_id', $otherEeMap->keys())
            ->whereIn('away_enrolment_event_id', $otherEeMap->keys())
            ->get()
            ->mapWithKeys(function ($match) use ($otherEeMap) {
                $a = $otherEeMap[$match->home_enrolment_event_id] ?? null;
                $b = $otherEeMap[$match->away_enrolment_event_id] ?? null;
                if (! $a || ! $b) return [];
                $key = min($a, $b) . '_' . max($a, $b);
                return [$key => true];
            });

        if ($priorPairs->isEmpty()) return $competitors;

        $arr = $competitors->all();
        $n   = count($arr);
        for ($i = 0; $i < $n - 2; $i += 2) {
            $a = $profileById[$arr[$i]->id] ?? null;
            $b = $profileById[$arr[$i + 1]->id] ?? null;
            if ($a && $b && $priorPairs->has(min($a, $b) . '_' . max($a, $b))) {
                [$arr[$i + 1], $arr[$i + 2]] = [$arr[$i + 2], $arr[$i + 1]];
            }
        }
        return collect($arr);
    }
}
