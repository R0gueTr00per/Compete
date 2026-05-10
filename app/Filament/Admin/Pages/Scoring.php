<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use App\Services\BracketService;
use App\Services\EnrolmentService;
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

    public array $judgeScores           = [];
    public array $pointsInput          = [];
    public array $placementInput       = [];
    public array $tiebreakerJudgeInputs = [];
    public array $rollcallPresent       = [];
    public bool  $rollcallMode          = false;

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

        $comp = Competition::find($this->competition_id);
        if (! $comp || $comp->status !== 'running') return collect();

        $query = Division::whereHas('competitionEvent', fn ($q) =>
            $q->where('competition_id', $this->competition_id)
              ->whereIn('status', ['scheduled', 'running', 'complete'])
        )
        ->with(['competitionEvent'])
        ->when($this->filter_location, fn ($q) => $q->where('location_label', $this->filter_location))
        ->whereIn('status', ['pending', 'assigned', 'running', 'complete', 'cancelled'])
        ->orderByRaw("CASE status WHEN 'running' THEN 0 WHEN 'assigned' THEN 1 WHEN 'pending' THEN 2 WHEN 'complete' THEN 3 WHEN 'cancelled' THEN 4 ELSE 5 END")
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
        $this->division_id      = ($this->division_id === $divisionId) ? null : $divisionId;
        $this->judgeScores      = [];
        $this->pointsInput      = [];
        $this->placementInput   = [];
        $this->rollcallPresent  = [];
        $this->rollcallMode     = true;
    }

    public function toggleRollcallPresent(int $eeId): void
    {
        if (in_array($eeId, $this->rollcallPresent)) {
            $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, [$eeId]));
        } else {
            $this->rollcallPresent[] = $eeId;
        }
    }

    public function toggleRollcall(): void
    {
        if ($this->rollcallMode) {
            // Transitioning to scoring — mark anyone not confirmed as absent
            $activeEeIds = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
                ->pluck('id');

            $svc = app(EnrolmentService::class);
            foreach ($activeEeIds as $eeId) {
                if (! in_array($eeId, $this->rollcallPresent)) {
                    $ee = EnrolmentEvent::find($eeId);
                    if ($ee) {
                        $svc->removeParticipant($ee, auth()->user(), 'No-show at rollcall');
                    }
                }
            }

            $this->rollcallMode = false;
        } else {
            // Going back to rollcall — pre-populate present list from still-active EEs
            $this->rollcallPresent = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
                ->pluck('id')
                ->toArray();
            $this->rollcallMode = true;
        }
    }

    public function removeNoShow(int $enrolmentEventId): void
    {
        $ee = EnrolmentEvent::find($enrolmentEventId);
        if (! $ee || $ee->division_id !== $this->division_id) return;

        app(EnrolmentService::class)->removeParticipant($ee, auth()->user(), 'No-show at rollcall');
        Notification::make()->title('Marked as absent.')->warning()->send();
    }

    public function undoRollcallRemoval(int $enrolmentEventId): void
    {
        $ee = EnrolmentEvent::find($enrolmentEventId);
        if (! $ee || $ee->division_id !== $this->division_id) return;

        app(EnrolmentService::class)->readdParticipant($ee);
        Notification::make()->title('Competitor reinstated.')->success()->send();
    }

    public function getRollcallRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $absent = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', true)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with(['enrolment.competitor.competitorProfile'])
            ->get()
            ->map(fn ($ee) => (object) [
                'ee_id'  => $ee->id,
                'name'   => ($p = $ee->enrolment->competitor?->competitorProfile)
                    ? $p->first_name . ' ' . $p->surname
                    : ($ee->enrolment->competitor?->name ?? '(unknown)'),
                'absent' => true,
            ]);

        $active = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with(['enrolment.competitor.competitorProfile'])
            ->get()
            ->map(fn ($ee) => (object) [
                'ee_id'  => $ee->id,
                'name'   => ($p = $ee->enrolment->competitor?->competitorProfile)
                    ? $p->first_name . ' ' . $p->surname
                    : ($ee->enrolment->competitor?->name ?? '(unknown)'),
                'absent' => false,
            ]);

        return $active->sortBy('name')->merge($absent->sortBy('name'));
    }

    public function getSelectedDivision(): ?Division
    {
        if (! $this->division_id) return null;

        return Division::with('competitionEvent')->find($this->division_id);
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
                    'name'   => $this->resolveEeName($ee),
                ];
            })
            ->sortBy('name');
    }

    public function isTournament(): bool
    {
        return in_array($this->getTournamentFormat(), ['round_robin', 'single_elimination', 'double_elimination', 'repechage']);
    }

    public function isRoundRobin(): bool
    {
        return $this->getTournamentFormat() === 'round_robin';
    }

    public function getTournamentFormat(): ?string
    {
        $div = $this->getSelectedDivision();
        return $div?->competitionEvent->effectiveTournamentFormat();
    }

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
            ->with('enrolment.competitor.competitorProfile')
            ->get()
            ->sortBy(fn ($ee) => $this->resolveEeName($ee))
            ->values();

        $n = $competitors->count();
        if ($n < 2) {
            Notification::make()->title('Need at least 2 checked-in competitors.')->warning()->send();
            return;
        }

        app(BracketService::class)->generate($this->getSelectedDivision(), $competitors);

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

    public function clearBracketResult(int $matchId): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;

        // For repechage format, clearing any WB result invalidates the repechage bracket
        // (finalists may change), so delete the whole repechage bracket now.
        if ($match->bracket === 'winners') {
            $div = Division::with('competitionEvent')->find($match->division_id);
            if ($div?->competitionEvent->effectiveTournamentFormat() === 'repechage') {
                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'repechage')
                    ->delete();
            }
        }

        // Remove any matches created by this result's advancement
        $winner = $match->winnerId();
        if ($winner) {
            $nextSlot  = (int) ceil($match->bracket_slot / 2);
            $nextRound = $match->round + 1;

            // Clear the winner from the next match
            $next = RoundRobinMatch::where('division_id', $this->division_id)
                ->where('round', $nextRound)
                ->where('bracket', $match->bracket)
                ->where('bracket_slot', $nextSlot)
                ->first();

            if ($next) {
                if ($next->home_enrolment_event_id === $winner) {
                    $next->update(['home_enrolment_event_id' => null]);
                } elseif ($next->away_enrolment_event_id === $winner) {
                    $next->update(['away_enrolment_event_id' => null]);
                }
                // Delete the next match if both slots are now empty
                $next->refresh();
                if ($next->home_enrolment_event_id === null && $next->away_enrolment_event_id === null) {
                    $next->delete();
                }
            }

            // Delete any grand final that referenced this winner
            RoundRobinMatch::where('division_id', $this->division_id)
                ->where('bracket', 'grand_final')
                ->where(fn ($q) => $q->where('home_enrolment_event_id', $winner)
                    ->orWhere('away_enrolment_event_id', $winner))
                ->delete();
        }

        $match->update(['home_result' => null]);
        $this->applyBracketPlacements();
        Notification::make()->success()->title('Result cleared.')->send();
    }

    public function resetBracket(): void
    {
        if (! $this->division_id) return;

        RoundRobinMatch::where('division_id', $this->division_id)->delete();
        Notification::make()->success()->title('Bracket cleared.')->send();
    }

    private function applyBracketPlacements(): void
    {
        $service = app(ScoringService::class);
        $matches = RoundRobinMatch::where('division_id', $this->division_id)
            ->whereNotNull('home_result')
            ->get();

        $format = $this->getTournamentFormat();

        if ($format === 'round_robin') {
            $allEeIds = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->pluck('id');

            if ($matches->isEmpty()) return;

            $winCounts = $allEeIds->mapWithKeys(fn ($id) => [$id => 0])->toArray();
            foreach ($matches as $m) {
                $winnerId = $m->winnerId();
                if ($winnerId && isset($winCounts[$winnerId])) {
                    $winCounts[$winnerId]++;
                }
            }

            arsort($winCounts);

            $rank        = 1;
            $prevWins    = null;
            $countAtRank = 0;
            foreach ($winCounts as $eeId => $wins) {
                if ($prevWins !== null && $wins < $prevWins) {
                    $rank += $countAtRank;
                    $countAtRank = 0;
                }
                $this->setBracketPlacement((int) $eeId, $rank, $service);
                $prevWins = $wins;
                $countAtRank++;
            }
            return;
        } elseif ($format === 'double_elimination') {
            $gf = $matches->firstWhere('bracket', 'grand_final');
            if ($gf?->winnerId()) {
                $this->setBracketPlacement($gf->winnerId(), 1, $service);
                if ($gf->loserId()) $this->setBracketPlacement($gf->loserId(), 2, $service);
            }
        } elseif ($format === 'repechage') {
            // WB final determines 1st/2nd; repechage bracket winner gets 3rd.
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service);
            }
            $repMatches   = $matches->where('bracket', 'repechage');
            $maxRepRound  = $repMatches->max('round');
            $repFinal     = $repMatches->where('round', $maxRepRound)->first();
            if ($repFinal?->winnerId()) $this->setBracketPlacement($repFinal->winnerId(), 3, $service);
        } else {
            // Single elimination — highest WB round determines 1st/2nd
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service);
            }

            // Semi-final losers → 3rd
            if ($wbFinalRound >= 2) {
                foreach ($matches->where('bracket', 'winners')->where('round', $wbFinalRound - 1) as $semi) {
                    if ($semi->loserId()) $this->setBracketPlacement($semi->loserId(), 3, $service);
                }
            }
        }
    }

    private function setBracketPlacement(int $eeId, int $placement, ScoringService $service): void
    {
        $ee = EnrolmentEvent::with('result')->find($eeId);
        if (! $ee) return;
        $result = $ee->result ?? $service->getOrCreateResult($ee);
        if (! $result->placement_overridden) {
            $result->update(['placement' => $placement]);
        }
    }

    public function getBracketData(): array
    {
        $all = RoundRobinMatch::where('division_id', $this->division_id)
            ->with([
                'homeEnrolmentEvent.enrolment.competitor.competitorProfile',
                'awayEnrolmentEvent.enrolment.competitor.competitorProfile',
            ])
            ->orderBy('bracket')->orderBy('round')->orderBy('bracket_slot')
            ->get();

        $eeNames = [];
        foreach ($all as $m) {
            foreach ([$m->homeEnrolmentEvent, $m->awayEnrolmentEvent] as $ee) {
                if ($ee && ! isset($eeNames[$ee->id])) {
                    $eeNames[$ee->id] = $this->resolveEeName($ee);
                }
            }
        }

        $map = ['winners' => [], 'losers' => [], 'repechage' => [], 'grand_final' => []];
        foreach ($all as $m) {
            $map[$m->bracket][$m->round][] = (object) [
                'id'          => $m->id,
                'slot'        => $m->bracket_slot,
                'home_id'     => $m->home_enrolment_event_id,
                'away_id'     => $m->away_enrolment_event_id,
                'home_name'   => isset($eeNames[$m->home_enrolment_event_id]) ? $eeNames[$m->home_enrolment_event_id] : '—',
                'away_name'   => $m->away_enrolment_event_id
                    ? ($eeNames[$m->away_enrolment_event_id] ?? '—')
                    : ($m->home_result === null ? 'Waiting...' : 'BYE'),
                'home_result' => $m->home_result,
                'is_bye'      => $m->isBye(),
                'is_pending'  => $m->isPending() && ! $m->isBye(),
                'winner_id'   => $m->winnerId(),
                'loser_id'    => $m->loserId(),
            ];
        }

        return $map;
    }

    private function resolveEeName(?EnrolmentEvent $ee): string
    {
        if (! $ee) return '—';
        $profile = $ee->enrolment->competitor?->competitorProfile;
        return $profile
            ? $profile->first_name . ' ' . $profile->surname
            : ($ee->enrolment->competitor?->name ?? '—');
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

    public function getTiedGroups(): \Illuminate\Support\Collection
    {
        $method = $this->getScoringMethod();
        if (! in_array($method, ['judges_total', 'judges_average'])) {
            return collect();
        }

        // Groups that need a tiebreaker: same total_score, no tiebreaker_score yet on any member
        return $this->getCompetitorRows()
            ->filter(fn ($row) => $row->result->total_score !== null && ! $row->result->disqualified)
            ->groupBy(fn ($row) => (string) $row->result->total_score)
            ->filter(fn ($group) => $group->count() > 1)
            ->filter(fn ($group) => $group->every(fn ($row) => $row->result->tiebreaker_score === null))
            ->values();
    }

    public function getStillTiedAfterTiebreaker(): \Illuminate\Support\Collection
    {
        $method = $this->getScoringMethod();
        if (! in_array($method, ['judges_total', 'judges_average'])) {
            return collect();
        }

        // Groups where all members have a tiebreaker_score but they're still equal
        return $this->getCompetitorRows()
            ->filter(fn ($row) => $row->result->tiebreaker_score !== null && ! $row->result->disqualified)
            ->groupBy(fn ($row) => (string) $row->result->total_score . '|' . (string) $row->result->tiebreaker_score)
            ->filter(fn ($group) => $group->count() > 1)
            ->values();
    }

    public function saveTiebreakerScores(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        $inputs  = $this->tiebreakerJudgeInputs[$resultId] ?? [];
        $method  = $this->getScoringMethod();
        $scores  = collect($inputs)->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (float) $v);

        if ($scores->isEmpty()) {
            Notification::make()->title('Enter at least one judge score.')->warning()->send();
            return;
        }

        $total = $method === 'judges_average'
            ? round($scores->avg(), 3)
            : round($scores->sum(), 3);

        app(ScoringService::class)->saveTiebreakerScore($result, $total);
        Notification::make()->title('Tiebreaker score saved.')->success()->send();
    }

    public function clearTiebreakerScore(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        unset($this->tiebreakerJudgeInputs[$resultId]);
        app(ScoringService::class)->clearTiebreakerScore($result);
        Notification::make()->title('Tiebreaker score cleared.')->success()->send();
    }

    public function markDivisionComplete(): void
    {
        if (! $this->division_id) return;

        if ($this->isTournament()) {
            // All non-bye bracket matches must have a result
            $pending = RoundRobinMatch::where('division_id', $this->division_id)
                ->whereNotNull('away_enrolment_event_id')
                ->whereNull('home_result')
                ->count();

            if ($pending > 0) {
                Notification::make()
                    ->warning()
                    ->title("Cannot complete — {$pending} bracket match(es) still pending.")
                    ->send();
                return;
            }
        } else {
            $method = $this->getScoringMethod();

            if (in_array($method, ['judges_total', 'judges_average'])) {
                $missing = $this->getCompetitorRows()
                    ->filter(fn ($row) => ! $row->result->disqualified && $row->result->total_score === null)
                    ->count();

                if ($missing > 0) {
                    Notification::make()
                        ->warning()
                        ->title("Cannot complete — {$missing} competitor(s) have no score entered.")
                        ->send();
                    return;
                }
            } elseif ($method === 'win_loss') {
                $missing = $this->getCompetitorRows()
                    ->filter(fn ($row) => ! $row->result->disqualified && $row->result->win_loss === null)
                    ->count();

                if ($missing > 0) {
                    Notification::make()
                        ->warning()
                        ->title("Cannot complete — {$missing} competitor(s) have no result recorded.")
                        ->send();
                    return;
                }
            }
        }

        Division::find($this->division_id)?->update(['status' => 'complete']);
        $this->division_id = null;
        Notification::make()->title('Division marked complete.')->success()->send();
    }

    public function cancelDivision(): void
    {
        if (! $this->division_id) return;

        Division::find($this->division_id)?->update(['status' => 'cancelled']);
        $this->division_id = null;
        Notification::make()->title('Division cancelled.')->warning()->send();
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
