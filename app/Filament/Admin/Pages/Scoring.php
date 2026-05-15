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

    public array $judgeScores            = [];
    public array $pointsInput            = [];
    public array $placementInput         = [];
    public array $tiebreakerJudgeInputs  = [];
    public array $rollcallPresent        = [];
    public array $bracketScoreInput      = [];
    public array $savedResultIds         = [];
    public array $completedRollcallDivisions = [];
    public bool  $rollcallMode           = false;
    public bool  $panelOpen             = false;
    public bool  $bracketExists          = false;
    public bool  $placementOverrideMode  = false;

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

        $this->loadRollcallFromSession();
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
        ->whereIn('status', ['pending', 'assigned', 'running', 'complete'])
        ->orderBy('code');

        return $query->get()->toBase()->map(function (Division $div) {
            $base = EnrolmentEvent::where('division_id', $div->id)
                ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'));

            $checkedInCount   = (clone $base)->count();
            $competitorsCount = (clone $base)->where('removed', false)->count();

            return (object) [
                'division'          => $div,
                'checked_in_count'  => $checkedInCount,
                'competitors_count' => $competitorsCount,
            ];
        });
    }

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

    private function clearScoringMemory(): void
    {
        $this->judgeScores           = [];
        $this->pointsInput           = [];
        $this->placementInput        = [];
        $this->tiebreakerJudgeInputs = [];
        $this->bracketScoreInput     = [];
        $this->savedResultIds        = [];
        $this->rollcallMode          = true;
        $this->placementOverrideMode = false;
        $this->bracketExists         = false;
        $this->panelOpen             = false;
        // rollcallPresent is intentionally NOT cleared here so ticks survive
        // division switches. Only cancelScoring() resets it explicitly.
    }

    public function selectDivision(int $divisionId): void
    {
        // Same division clicked — toggle the panel open/closed (state preserved either way).
        if ($this->division_id === $divisionId) {
            $this->panelOpen = ! $this->panelOpen;
            return;
        }

        // Different division — discard stale state and load fresh from DB.
        $this->clearScoringMemory();
        $this->division_id   = $divisionId;
        $this->panelOpen     = true;
        $this->bracketExists = RoundRobinMatch::where('division_id', $divisionId)->exists();

        $division = Division::find($divisionId);

        if ($division?->status === 'complete') {
            $this->rollcallMode = false;
            return;
        }

        // If scoring was already in progress before the page refresh, skip back to the scoring view.
        // Signals: bracket generated, any competitor marked absent, or any scores already saved.
        $eeIds     = EnrolmentEvent::where('division_id', $divisionId)->pluck('id');
        $hasAbsent = EnrolmentEvent::where('division_id', $divisionId)->where('removed', true)->exists();
        $hasScores = $this->bracketExists
            || Result::whereIn('enrolment_event_id', $eeIds)
                ->where(fn ($q) => $q->whereNotNull('total_score')->orWhereNotNull('win_loss'))
                ->exists();

        if ($hasAbsent || $hasScores) {
            $this->rollcallMode = false;
            if (! in_array($divisionId, $this->completedRollcallDivisions)) {
                $this->completedRollcallDivisions[] = $divisionId;
            }
            $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
                ->whereNotNull('total_score')
                ->pluck('id')
                ->toArray();
        } else {
            $this->rollcallMode = true;
        }
    }

    public function deselectDivision(): void
    {
        $this->division_id = null;
        $this->clearScoringMemory();
    }

    public function toggleRollcallPresent(int $eeId): void
    {
        if (in_array($eeId, $this->rollcallPresent)) {
            $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, [$eeId]));
        } else {
            $this->rollcallPresent[] = $eeId;
        }

        $this->saveRollcallToSession();
    }

    public function toggleRollcall(): void
    {
        if ($this->rollcallMode) {
            // Transitioning to scoring — mark anyone not confirmed as absent
            $activeEeIds = EnrolmentEvent::where('division_id', $this->division_id)
                ->where('removed', false)
                ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
                ->pluck('id');

            $absentIds = $activeEeIds->diff($this->rollcallPresent);
            if ($absentIds->isNotEmpty()) {
                EnrolmentEvent::whereIn('id', $absentIds)->update(['removed' => true]);
            }

            if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
                $this->completedRollcallDivisions[] = $this->division_id;
            }
            $this->rollcallMode = false;
        } else {
            // Going back to rollcall — clear scores and bracket, restore absent flags.
            // rollcallPresent is intentionally preserved so previous ticks are still shown.
            $this->completedRollcallDivisions = array_values(array_diff($this->completedRollcallDivisions, [$this->division_id]));
            RoundRobinMatch::where('division_id', $this->division_id)->delete();

            $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
            Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
                $result->judgeScores()->delete();
                $result->update([
                    'total_score'          => null,
                    'tiebreaker_score'     => null,
                    'placement'            => null,
                    'placement_overridden' => false,
                    'win_loss'             => null,
                ]);
            });

            EnrolmentEvent::where('division_id', $this->division_id)->update(['removed' => false]);

            $this->judgeScores           = [];
            $this->savedResultIds        = [];
            $this->tiebreakerJudgeInputs = [];
            $this->pointsInput           = [];
            $this->placementInput        = [];
            $this->bracketScoreInput     = [];
            $this->bracketExists         = false;
            $this->rollcallMode          = true;
        }
    }

    public function removeNoShow(int $enrolmentEventId): void
    {
        $ee = EnrolmentEvent::find($enrolmentEventId);
        if (! $ee || $ee->division_id !== $this->division_id) return;

        $ee->update(['removed' => true]);
        Notification::make()->title('Marked as absent.')->warning()->send();
    }

    public function undoRollcallRemoval(int $enrolmentEventId): void
    {
        $ee = EnrolmentEvent::find($enrolmentEventId);
        if (! $ee || $ee->division_id !== $this->division_id) return;

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
            $result->judgeScores()->delete();
            $result->update([
                'total_score'          => null,
                'tiebreaker_score'     => null,
                'placement'            => null,
                'placement_overridden' => false,
                'win_loss'             => null,
            ]);
        });
        $this->judgeScores          = [];
        $this->savedResultIds       = [];
        $this->tiebreakerJudgeInputs = [];

        $ee->update(['removed' => false]);
        Notification::make()->title('Competitor added.')->success()->send();
    }

    public function getRollcallRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $absent = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', true)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with(['enrolment.competitor.competitorProfile'])
            ->get()->toBase()
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
            ->get()->toBase()
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

        $division = $this->getSelectedDivision();

        return EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
            ->with([
                'enrolment.competitor.competitorProfile',
                'result.judgeScores',
            ])
            ->get()->toBase()
            ->map(function (EnrolmentEvent $ee) use ($division) {
                $result = $ee->result
                    ?? app(ScoringService::class)->getOrCreateResult($ee);

                if (! isset($this->judgeScores[$result->id])) {
                    $scores = [];
                    foreach ($result->judgeScores->where('is_tiebreaker', false) as $js) {
                        $scores[$js->judge_number] = number_format((float) $js->score, 1);
                    }
                    if (empty($scores) && $division?->competitionEvent?->default_score !== null) {
                        $judgeCount = $division->competitionEvent->effectiveJudgeCount();
                        for ($i = 1; $i <= $judgeCount; $i++) {
                            $scores[$i] = number_format((float) $division->competitionEvent->default_score, 1);
                        }
                    }
                    $this->judgeScores[$result->id] = $scores;
                }

                if (! isset($this->tiebreakerJudgeInputs[$result->id])) {
                    $tbScores = $result->judgeScores->where('is_tiebreaker', true);
                    if ($tbScores->isNotEmpty()) {
                        foreach ($tbScores as $js) {
                            $this->tiebreakerJudgeInputs[$result->id][$js->judge_number] = (float) $js->score;
                        }
                    }
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
        $rows   = $this->getCompetitorRows();

        if ($rows->isEmpty()) return false;

        return $rows->every(fn ($row) => $row->result->disqualified || match ($method) {
            'judges_total', 'judges_average' => $row->result->total_score !== null,
            'win_loss'                       => $row->result->win_loss !== null,
            'first_to_n'                     => $row->result->total_score !== null,
            default                          => true,
        });
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

        $this->bracketExists = true;
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

    public function recordBracketScore(int $matchId): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;
        if (! $match->isPending()) return;

        $homeScore = isset($this->bracketScoreInput[$matchId]['home']) && $this->bracketScoreInput[$matchId]['home'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['home']
            : null;
        $awayScore = isset($this->bracketScoreInput[$matchId]['away']) && $this->bracketScoreInput[$matchId]['away'] !== ''
            ? (float) $this->bracketScoreInput[$matchId]['away']
            : null;

        if ($homeScore === null || $awayScore === null) {
            Notification::make()->title('Enter scores for both competitors.')->warning()->send();
            return;
        }

        $scoringMethod = $this->getScoringMethod();
        if ($scoringMethod === 'first_to_n') {
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
                if ($homeScore != $target && $awayScore != $target) {
                    Notification::make()->title("One competitor must have {$target} points.")->warning()->send();
                    return;
                }
            }
        }

        if ($homeScore === $awayScore) {
            Notification::make()->title('Scores are tied — a winner cannot be determined.')->warning()->send();
            return;
        }

        $match->update(['home_score' => $homeScore, 'away_score' => $awayScore]);

        $homeWins = $homeScore > $awayScore;
        $match->update(['home_result' => $homeWins ? 'win' : 'loss']);

        app(BracketService::class)->advance($match->fresh());
        $this->applyBracketPlacements();

        Notification::make()->success()->title('Score recorded.')->send();
    }

    public function clearBracketResult(int $matchId): void
    {
        $match = RoundRobinMatch::find($matchId);
        if (! $match || $match->division_id !== $this->division_id) return;

        $format = $this->getTournamentFormat();
        $winner = $match->winnerId();
        $loser  = $match->loserId();

        if ($match->bracket === 'winners') {
            // Clearing any WB result invalidates the repechage / 3rd-place bracket
            if (in_array($format, ['repechage', 'se_3rd_place'])) {
                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'repechage')
                    ->delete();
            }

            if ($winner) {
                // Remove winner from next WB match
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'winners', (int) ceil($match->bracket_slot / 2));

                // DE: remove the loser from the LB slot they were sent to
                if ($format === 'double_elimination' && $loser) {
                    [$lbRound, $lbSlot] = $match->round === 1
                        ? [1, (int) ceil($match->bracket_slot / 2)]
                        : [2 * ($match->round - 1), $match->bracket_slot];
                    $this->clearCompetitorFromSlot($loser, $lbRound, 'losers', $lbSlot);
                }

                // Delete any grand final referencing this winner
                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'grand_final')
                    ->where(fn ($q) => $q->where('home_enrolment_event_id', $winner)
                        ->orWhere('away_enrolment_event_id', $winner))
                    ->delete();
            }
        } elseif ($match->bracket === 'losers') {
            if ($winner) {
                // LB slot formula: odd rounds keep slot, even rounds merge (ceil)
                $nextSlot = ($match->round % 2 === 1)
                    ? $match->bracket_slot
                    : (int) ceil($match->bracket_slot / 2);
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'losers', $nextSlot);

                RoundRobinMatch::where('division_id', $this->division_id)
                    ->where('bracket', 'grand_final')
                    ->where(fn ($q) => $q->where('home_enrolment_event_id', $winner)
                        ->orWhere('away_enrolment_event_id', $winner))
                    ->delete();
            }
        } elseif ($match->bracket === 'repechage') {
            if ($winner) {
                // Repechage is a mini SE bracket
                $this->clearCompetitorFromSlot($winner, $match->round + 1, 'repechage', (int) ceil($match->bracket_slot / 2));
            }
        }
        // grand_final: no downstream — just clear the result below

        $match->update(['home_result' => null, 'home_score' => null, 'away_score' => null]);
        $this->applyBracketPlacements();
        Notification::make()->success()->title('Result cleared.')->send();
    }

    private function clearCompetitorFromSlot(int $eeId, int $round, string $bracket, int $slot): void
    {
        $match = RoundRobinMatch::where('division_id', $this->division_id)
            ->where('round', $round)
            ->where('bracket', $bracket)
            ->where('bracket_slot', $slot)
            ->first();

        if (! $match) return;

        if ($match->home_enrolment_event_id === $eeId) {
            $match->update(['home_enrolment_event_id' => null]);
        } elseif ($match->away_enrolment_event_id === $eeId) {
            $match->update(['away_enrolment_event_id' => null]);
        }

        $match->refresh();
        if ($match->home_enrolment_event_id === null && $match->away_enrolment_event_id === null) {
            $match->delete();
        }
    }

    public function resetBracket(): void
    {
        if (! $this->division_id) return;

        RoundRobinMatch::where('division_id', $this->division_id)->delete();
        $this->bracketExists = false;
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
        } elseif ($format === 'se_3rd_place') {
            $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
            $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
            if ($wbFinal?->winnerId()) {
                $this->setBracketPlacement($wbFinal->winnerId(), 1, $service);
                if ($wbFinal->loserId()) $this->setBracketPlacement($wbFinal->loserId(), 2, $service);
            }
            // 3rd: winner of 3rd-place match; fall back to lone semi-final loser if no match was created
            $repFinal = $matches->where('bracket', 'repechage')->sortByDesc('round')->first();
            if ($repFinal?->winnerId()) {
                $this->setBracketPlacement($repFinal->winnerId(), 3, $service);
            } elseif ($wbFinalRound >= 2) {
                foreach ($matches->where('bracket', 'winners')->where('round', $wbFinalRound - 1) as $semi) {
                    if ($semi->loserId()) $this->setBracketPlacement($semi->loserId(), 3, $service);
                }
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
                'home_score'  => $m->home_score,
                'away_score'  => $m->away_score,
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

        return $div->competitionEvent->effectiveJudgeCount();
    }

    public function getTargetScore(): ?int
    {
        $div = $this->getSelectedDivision();
        if (! $div) return null;

        return $div->competitionEvent->effectiveTargetScore();
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

        if (! in_array($resultId, $this->savedResultIds)) {
            $this->savedResultIds[] = $resultId;
        }

        Notification::make()->title('Scores saved.')->success()->send();
    }

    public function undoJudgeScores(int $resultId): void
    {
        $this->savedResultIds = array_values(array_diff($this->savedResultIds, [$resultId]));
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

        $service = app(ScoringService::class);
        $service->overridePlacement($result, $placement);

        if ($result->fresh()->tiebreaker_score !== null) {
            $service->clearTiebreakerScore($result);
            unset($this->tiebreakerJudgeInputs[$resultId]);
        }

        Notification::make()->title('Placement overridden.')->warning()->send();
    }

    public function clearOverride(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->clearPlacementOverride($result);
        Notification::make()->title('Override cleared — auto-ranked.')->success()->send();
    }

    public function togglePlacementOverrideMode(): void
    {
        $this->placementOverrideMode = ! $this->placementOverrideMode;

        if (! $this->placementOverrideMode) {
            foreach ($this->getCompetitorRows() as $row) {
                app(ScoringService::class)->clearPlacementOverride($row->result);
            }
            Notification::make()->title('Auto-ranking restored.')->success()->send();
        }
    }

    public function toggleDisqualify(int $resultId): void
    {
        $result = Result::find($resultId);
        if (! $result) return;

        app(ScoringService::class)->toggleDisqualify($result);
        $label = $result->fresh()->disqualified ? 'Disqualified.' : 'Disqualification removed.';
        Notification::make()->title($label)->warning()->send();
    }

    public function hasSavedScores(): bool
    {
        if (! $this->division_id) return false;

        return Result::whereHas('enrolmentEvent', fn ($q) => $q->where('division_id', $this->division_id))
            ->whereNotNull('total_score')
            ->exists();
    }

    public function resetJudgeScores(): void
    {
        if (! $this->division_id) return;

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
            $result->judgeScores()->delete();
            $result->update([
                'total_score'           => null,
                'tiebreaker_score'      => null,
                'placement'             => null,
                'placement_overridden'  => false,
            ]);
        });

        $this->judgeScores           = [];
        $this->tiebreakerJudgeInputs = [];
        $this->placementOverrideMode = false;
        Notification::make()->title('Scores cleared.')->success()->send();
    }

    public function cancelScoring(): void
    {
        if (! $this->division_id) return;

        RoundRobinMatch::where('division_id', $this->division_id)->delete();

        EnrolmentEvent::where('division_id', $this->division_id)->update(['removed' => false]);

        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        Result::whereIn('enrolment_event_id', $eeIds)->each(function (Result $result) {
            $result->judgeScores()->delete();
            $result->update([
                'total_score'          => null,
                'tiebreaker_score'     => null,
                'placement'            => null,
                'placement_overridden' => false,
                'win_loss'             => null,
            ]);
        });

        $this->completedRollcallDivisions = array_values(array_diff($this->completedRollcallDivisions, [$this->division_id]));
        $cancelledEeIds = $eeIds->toArray();
        $this->rollcallPresent = array_values(array_diff($this->rollcallPresent, $cancelledEeIds));
        $this->saveRollcallToSession();
        $this->division_id = null;
        $this->clearScoringMemory();
    }

    public function getTiedGroups(): \Illuminate\Support\Collection
    {
        $method = $this->getScoringMethod();
        if (! in_array($method, ['judges_total', 'judges_average'])) {
            return collect();
        }

        $rows = $this->getCompetitorRows();

        // Tiebreaker only activates once ALL non-DQ competitors have had their score saved
        $allSaved = $rows->every(fn ($row) => $row->result->disqualified || in_array($row->result->id, $this->savedResultIds));
        if (! $allSaved) {
            return collect();
        }

        $division     = $this->getSelectedDivision();
        $defaultScore = $division?->competitionEvent->default_score;
        $judgeCount   = $this->getJudgeCount();

        // Build score groups sorted highest-first and track cumulative placement.
        // A tied group only needs a tiebreaker if its starting position is within medal positions (≤ 3).
        $scoreGroups = $rows
            ->filter(fn ($row) => $row->result->total_score !== null && ! $row->result->disqualified)
            ->groupBy(fn ($row) => (string) $row->result->total_score)
            ->sortByDesc(fn ($group, $key) => (float) $key);

        $cumulative  = 0;
        $tiedGroups  = collect();

        foreach ($scoreGroups as $group) {
            $startingPosition = $cumulative + 1;

            if ($group->count() > 1 && $startingPosition <= 3) {
                if ($defaultScore !== null) {
                    foreach ($group as $row) {
                        $resultId = $row->result->id;
                        if (! isset($this->tiebreakerJudgeInputs[$resultId])) {
                            for ($j = 1; $j <= $judgeCount; $j++) {
                                $this->tiebreakerJudgeInputs[$resultId][$j] = (float) $defaultScore;
                            }
                        }
                    }
                }

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
        if (! in_array($method, ['judges_total', 'judges_average'])) {
            return collect();
        }

        $rows = $this->getCompetitorRows();

        // Tiebreaker only activates once ALL non-DQ competitors have had their score saved
        $allSaved = $rows->every(fn ($row) => $row->result->disqualified || in_array($row->result->id, $this->savedResultIds));
        if (! $allSaved) {
            return collect();
        }

        // Groups where all members have a tiebreaker_score but they're still equal
        return $rows
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

        $service = app(ScoringService::class);
        foreach ($inputs as $judgeNum => $score) {
            if ($score !== null && $score !== '') {
                $service->submitJudgeScore($result, (int) $judgeNum, (float) $score, true);
            }
        }
        $service->saveTiebreakerScore($result, $total);
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

    public function reactivateDivision(): void
    {
        if (! $this->division_id) return;

        Division::find($this->division_id)?->update(['status' => 'assigned']);

        // Pre-populate savedResultIds so the tiebreaker gate works immediately
        $eeIds = EnrolmentEvent::where('division_id', $this->division_id)->pluck('id');
        $this->savedResultIds = Result::whereIn('enrolment_event_id', $eeIds)
            ->whereNotNull('total_score')
            ->pluck('id')
            ->toArray();

        // Keep the competitor count visible in the division list
        if (! in_array($this->division_id, $this->completedRollcallDivisions)) {
            $this->completedRollcallDivisions[] = $this->division_id;
        }

        Notification::make()->title('Division re-activated — scoring is now editable.')->warning()->send();
    }

    public function markDivisionComplete(): void
    {
        if (! $this->division_id) return;

        if ($this->isTournament()) {
            if (! $this->bracketExists) {
                Notification::make()
                    ->warning()
                    ->title('Cannot complete — bracket has not been generated yet.')
                    ->send();
                return;
            }

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
            } elseif ($method === 'first_to_n') {
                $missing = $this->getCompetitorRows()
                    ->filter(fn ($row) => ! $row->result->disqualified && $row->result->total_score === null)
                    ->count();

                if ($missing > 0) {
                    Notification::make()
                        ->warning()
                        ->title("Cannot complete — {$missing} competitor(s) have no points recorded.")
                        ->send();
                    return;
                }
            }
        }

        Division::find($this->division_id)?->update(['status' => 'complete']);
        $this->division_id = null;
        $this->clearScoringMemory();
        Notification::make()->title('Division marked complete.')->success()->send();
    }

    public function updatedCompetitionId(): void
    {
        $this->filter_location = null;
        $this->division_id     = null;
        $this->rollcallPresent = [];
        $this->clearRollcallFromSession();
        $this->clearScoringMemory();
    }

    public function updatedFilterLocation(): void
    {
        $this->division_id = null;
        $this->clearScoringMemory();
    }
}
