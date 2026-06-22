<?php

namespace App\Livewire\OrgAdmin;

use App\Livewire\OrgAdmin\Concerns\HasDivisionScoring;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\MatchPenalty;
use App\Models\Result;
use App\Notifications\Notification;
use App\Services\ScoringService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

#[Lazy]
class JudgeScorePanel extends Component
{
    use HasDivisionScoring;

    #[Locked]
    public int $division_id = 0;

    public array $judgeScores               = [];
    public array $categoryScores            = [];
    public array $savedResultIds            = [];
    public array $pointsInput               = [];
    public array $placementInput            = [];
    public array $noteInput                 = [];
    public bool  $placementOverrideMode     = false;

    public function mount(int $divisionId): void
    {
        $this->division_id = $divisionId;

        $division = $this->selectedDivision;
        if ($division) {
            $this->placementOverrideMode = (bool) $division->placement_override_mode;

            $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
            $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
                ->where(fn ($q) => $q->whereNotNull('total_score')->orWhere('disqualified', true)->orWhere('forfeited', true))
                ->pluck('id')
                ->toArray();
        }
    }

    public function placeholder(): string
    {
        return '<div class="py-8 text-center text-sm text-gray-400">Loading scores…</div>';
    }

    public function render()
    {
        return view('livewire.org-admin.judge-score-panel', ['div' => $this->selectedDivision]);
    }

    // ─── Computed ────────────────────────────────────────────────────────────

    #[Computed]
    public function competitorRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $division = $this->selectedDivision;
        $filter   = $division?->competitionEvent?->division_filter ?? '';

        $dayId = $division?->competition_day_id;
        $eeCollection = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
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

                if (! isset($this->noteInput[$result->id])) {
                    $this->noteInput[$result->id] = $result->note ?? '';
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

    public function isScoringComplete(): bool
    {
        if (! $this->division_id) return false;

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

    public function hasSavedScores(): bool
    {
        return ! empty($this->savedResultIds);
    }

    // ─── Tiebreaker helpers ───────────────────────────────────────────────────

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

        $this->dispatch('scores-saved', divisionId: $this->division_id);
        Notification::make()->title('Scores saved.')->success()->send();
    }

    public function undoJudgeScores(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        $service = app(ScoringService::class);

        if ($result->tiebreaker_score !== null) {
            $service->clearTiebreakerScore($result);
        }

        $result->judgeScores()->where('is_tiebreaker', false)->each(function ($js) {
            $js->judgeScoreDetails()->delete();
            $js->delete();
        });
        $result->update(['total_score' => null]);
        $result->forceFill(['placement' => null, 'placement_overridden' => false])->save();

        MatchPenalty::where('result_id', $resultId)->delete();
        $result->disqualified = false;
        $result->forfeited    = false;
        $result->save();

        $service->autoRankDivision(Division::with('competitionEvent')->find($result->division_id));

        unset($this->placementInput[$resultId]);
        $this->savedResultIds = array_values(array_diff($this->savedResultIds, [$resultId]));
        $this->dispatch('scores-saved', divisionId: $this->division_id);
    }

    public function saveWinLoss(int $resultId, string $value): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;
        app(ScoringService::class)->recordWinLoss($result, $value);
        Notification::make()->title('Result recorded.')->success()->send();
    }

    #[Renderless]
    public function addPoints(int $resultId, float $amount): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

        $target = $this->getTargetScore();
        if ($target !== null && (($result->total_score ?? 0) + $amount) > $target) return;

        app(ScoringService::class)->addPoints($result, $amount);
    }

    #[Renderless]
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

        $this->judgeScores          = [];
        $this->categoryScores       = [];
        $this->savedResultIds       = [];
        $this->placementOverrideMode = false;
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
    }

    // ─── Tiebreaker actions ───────────────────────────────────────────────────

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

    // ─── Penalty passthrough ──────────────────────────────────────────────────

    public function openPenaltyModal(int $resultId, string $type, ?int $matchId = null): void
    {
        $this->dispatch('open-penalty-modal', resultId: $resultId, type: $type, matchId: $matchId);
    }

    public function undoPenalty(int $resultId, ?int $matchId = null): void
    {
        $this->dispatch('undo-penalty', resultId: $resultId, matchId: $matchId);
    }

    // ─── DQ applied listener ─────────────────────────────────────────────────

    #[On('dq-applied')]
    public function onDqApplied(int $resultId): void
    {
        if (! in_array($resultId, $this->savedResultIds)) {
            $this->savedResultIds[] = $resultId;
        }
    }

    // ─── Scoring cleared ─────────────────────────────────────────────────────

    #[On('scores-saved')]
    public function onScoresSaved(int $divisionId): void
    {
        if ($divisionId !== $this->division_id) return;
        unset($this->competitorRows);
    }

    #[On('division-reactivated')]
    public function onDivisionReactivated(): void
    {
        unset($this->selectedDivision);
    }

    #[On('scoring-cleared')]
    public function onScoringCleared(): void
    {
        $this->judgeScores          = [];
        $this->categoryScores       = [];
        $this->savedResultIds       = [];
        $this->pointsInput          = [];
        $this->placementInput       = [];
        $this->placementOverrideMode = false;
        unset($this->competitorRows);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private array $perfOrder = [];

    private function perfOrderSessionKey(): string
    {
        return 'scoring_perf_order_' . ($this->division_id ?? 0);
    }
}
