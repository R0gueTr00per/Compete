<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\Result;
use App\Services\ScoringService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Scoring extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $navigationLabel = 'Scoring';
    protected static string  $view            = 'filament.admin.pages.scoring';

    #[Url]
    public ?int $competition_id = null;

    #[Url]
    public ?string $filter_location = null;

    public ?int $division_id = null;

    public array $judgeScores   = [];
    public array $pointsInput   = [];
    public array $placementInput = [];

    public function mount(): void
    {
        if (! $this->competition_id) {
            $today = now()->toDateString();
            $comp  = Competition::whereIn('status', ['running', 'open'])
                ->where('competition_date', $today)->first()
                ?? Competition::whereIn('status', ['running', 'open'])
                    ->orderBy('competition_date')->first();

            if ($comp) {
                $this->competition_id = $comp->id;
            }
        }
    }

    public function getCompetitions(): array
    {
        return Competition::whereIn('status', ['open', 'running', 'closed', 'complete'])
            ->orderBy('competition_date', 'desc')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getLocations(): array
    {
        if (! $this->competition_id) return [];

        $comp      = Competition::find($this->competition_id);
        $locations = collect($comp?->locations ?? [])->filter()->values()->toArray();

        return array_combine($locations, $locations);
    }

    public function getDivisionList(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) return collect();

        $query = Division::whereHas('competitionEvent', function ($q) {
            $q->where('competition_id', $this->competition_id)
              ->whereIn('status', ['scheduled', 'running', 'complete']);

            if ($this->filter_location) {
                $q->where('location_label', $this->filter_location);
            }
        })
        ->with(['competitionEvent.eventType'])
        ->whereIn('status', ['pending', 'assigned', 'running', 'complete'])
        ->orderByRaw("CASE status WHEN 'running' THEN 0 WHEN 'assigned' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
        ->orderBy('code');

        return $query->get()->map(function (Division $div) {
            $checkedInCount = EnrolmentEvent::where('division_id', $div->id)
                ->where('removed', false)
                ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
                ->count();

            return (object) [
                'division'        => $div,
                'checked_in_count' => $checkedInCount,
            ];
        });
    }

    public function selectDivision(int $divisionId): void
    {
        $this->division_id    = ($this->division_id === $divisionId) ? null : $divisionId;
        $this->judgeScores    = [];
        $this->pointsInput    = [];
        $this->placementInput = [];
    }

    public function getSelectedDivision(): ?Division
    {
        if (! $this->division_id) return null;

        return Division::with('competitionEvent.eventType')->find($this->division_id);
    }

    public function getCompetitorRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        return EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with([
                'enrolment.competitor.competitorProfile',
                'result.judgeScores',
            ])
            ->get()
            ->map(function (EnrolmentEvent $ee) {
                $result = $ee->result
                    ?? app(ScoringService::class)->getOrCreateResult($ee);

                if (! isset($this->judgeScores[$result->id])) {
                    $scores = [];
                    foreach ($result->judgeScores as $js) {
                        $scores[$js->judge_number] = (float) $js->score;
                    }
                    $this->judgeScores[$result->id] = $scores;
                }

                if (! isset($this->pointsInput[$result->id]) && $result->total_score !== null) {
                    $this->pointsInput[$result->id] = (int) $result->total_score;
                }

                return (object) [
                    'ee'     => $ee,
                    'result' => $result,
                    'name'   => $ee->enrolment->competitor?->competitorProfile
                        ? $ee->enrolment->competitor->competitorProfile->surname . ', '
                          . $ee->enrolment->competitor->competitorProfile->first_name
                        : $ee->enrolment->competitor?->name,
                ];
            })
            ->sortBy('name');
    }

    public function getScoringMethod(): ?string
    {
        $div = $this->getSelectedDivision();
        if (! $div) return null;

        return $div->competitionEvent->effectiveScoringMethod();
    }

    public function getJudgeCount(): int
    {
        $div = $this->getSelectedDivision();
        if (! $div) return 3;

        return $div->competitionEvent->effectiveJudgeCount() ?? 3;
    }

    public function saveJudgeScores(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        $service = app(ScoringService::class);
        foreach ($this->judgeScores[$resultId] ?? [] as $judgeNum => $score) {
            if ($score !== null && $score !== '') {
                $service->submitJudgeScore($result, (int) $judgeNum, (float) $score);
            }
        }

        Notification::make()->title('Scores saved.')->success()->send();
    }

    public function saveWinLoss(int $resultId, string $value): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->recordWinLoss($result, $value);
        Notification::make()->title('Result recorded.')->success()->send();
    }

    public function savePoints(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->recordPoints($result, (int) ($this->pointsInput[$resultId] ?? 0));
        Notification::make()->title('Points saved.')->success()->send();
    }

    public function overridePlacement(int $resultId): void
    {
        $result    = Result::find($resultId);
        $placement = (int) ($this->placementInput[$resultId] ?? 0);

        if (! $result || $placement < 1) return;

        app(ScoringService::class)->overridePlacement($result, $placement);
        Notification::make()->title('Placement overridden.')->warning()->send();
    }

    public function clearOverride(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->clearPlacementOverride($result);
        Notification::make()->title('Override cleared — auto-ranked.')->success()->send();
    }

    public function toggleDisqualify(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->toggleDisqualify($result);
        $label = $result->fresh()->disqualified ? 'Disqualified.' : 'Disqualification removed.';
        Notification::make()->title($label)->warning()->send();
    }

    public function markDivisionComplete(): void
    {
        if (! $this->division_id) return;

        Division::find($this->division_id)?->update(['status' => 'complete']);
        $this->division_id = null;
        Notification::make()->title('Division marked complete.')->success()->send();
    }

    public function updatedCompetitionId(): void
    {
        $this->filter_location = null;
        $this->division_id     = null;
        $this->judgeScores     = [];
        $this->pointsInput     = [];
    }

    public function updatedFilterLocation(): void
    {
        $this->division_id = null;
        $this->judgeScores = [];
        $this->pointsInput = [];
    }
}
