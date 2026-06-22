<?php

namespace App\Livewire\OrgAdmin;

use App\Livewire\OrgAdmin\Concerns\HasDivisionScoring;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\MatchPenalty;
use App\Models\Result;
use App\Notifications\Notification;
use App\Services\ScoringService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
class TiebreakerPanel extends Component
{
    use HasDivisionScoring;

    #[Locked]
    public int $division_id = 0;

    public array $tbPendingFlat = [];
    public array $tbPendingCat  = [];
    public array $placementInput = [];

    public function mount(int $divisionId): void
    {
        $this->division_id = $divisionId;
    }

    public function placeholder(): string
    {
        return '<div></div>';
    }

    public function render()
    {
        return view('livewire.org-admin.tiebreaker-panel', [
            'div' => $this->selectedDivision,
        ]);
    }

    // ─── Tiebreaker ──────────────────────────────────────────────────────────

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
        $this->dispatch('scores-saved', divisionId: $this->division_id);
    }

    public function clearTiebreakerScore(int $resultId): void
    {
        $result = $this->findResult($resultId);
        if (! $result) return;

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
        $this->dispatch('scores-saved', divisionId: $this->division_id);
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

    public function openPenaltyModal(int $resultId, string $type): void
    {
        $this->dispatch('open-penalty-modal', resultId: $resultId, type: $type);
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
        $this->tbPendingFlat  = [];
        $this->tbPendingCat   = [];
        $this->placementInput = [];
        unset($this->competitorRows);
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

        return $eeCollection
            ->map(fn (EnrolmentEvent $ee) => (object) [
                'ee'     => $ee,
                'result' => $ee->result,
                'name'   => $this->resolveEeName($ee),
                'info'   => $this->buildRollcallInfo($ee, $filter),
            ])
            ->sortBy(fn ($row) => [$row->result->placement ?? 999, $row->name])
            ->values();
    }

    public function getTiedGroups(): \Illuminate\Support\Collection
    {
        $method = $this->getScoringMethod();
        if (! in_array($method, ['judges_total', 'judges_average'])) return collect();

        $rows     = $this->competitorRows;
        $allSaved = $rows->every(fn ($row) => $row->result->disqualified || $row->result->forfeited || $row->result->total_score !== null);
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

        $rows     = $this->competitorRows;
        $allSaved = $rows->every(fn ($row) => $row->result->disqualified || $row->result->forfeited || $row->result->total_score !== null);
        if (! $allSaved) return collect();

        return $rows
            ->filter(fn ($row) => $row->result->tiebreaker_score !== null && ! $row->result->disqualified)
            ->groupBy(fn ($row) => (string) $row->result->total_score . '|' . (string) $row->result->tiebreaker_score)
            ->filter(fn ($group) => $group->count() > 1)
            ->values();
    }
}
