<?php

namespace App\Livewire\OrgAdmin;

use App\Livewire\OrgAdmin\Concerns\HasDivisionScoring;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\MatchPenalty;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use App\Notifications\Notification;
use App\Services\BracketService;
use App\Services\ScoringService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
class BracketScoringPanel extends Component
{
    use HasDivisionScoring;

    #[Locked]
    public int $division_id = 0;

    #[Locked]
    public ?int $competition_id = null;

    public bool  $bracketExists         = false;
    public bool  $manualPairingMode     = false;
    public array $manualPairings        = [];
    public array $pairingCompetitorList = [];
    public array $bracketScoreInput     = [];

    public function mount(int $divisionId, ?int $competitionId = null): void
    {
        $this->division_id    = $divisionId;
        $this->competition_id = $competitionId;
        $this->bracketExists  = RoundRobinMatch::where('division_id', $divisionId)->exists();

        if (! $this->bracketExists && $this->isTournament()) {
            $this->generateBracket();
        }
    }

    public function placeholder(): string
    {
        return '<div class="py-8 text-center text-sm text-gray-400">Loading bracket…</div>';
    }

    public function render()
    {
        return view('livewire.org-admin.bracket-scoring-panel', [
            'div' => $this->selectedDivision,
        ]);
    }

    // ─── Rollcall event ──────────────────────────────────────────────────────

    #[On('rollcall-completed')]
    public function onRollcallCompleted(int $divisionId): void
    {
        if ($divisionId !== $this->division_id) return;
        if (! $this->isTournament()) return;
        if ($this->bracketExists) return;
        $this->generateBracket();
    }

    #[On('scoring-cleared')]
    public function onScoringCleared(): void
    {
        $this->bracketExists         = false;
        $this->manualPairingMode     = false;
        $this->manualPairings        = [];
        $this->pairingCompetitorList = [];
        $this->bracketScoreInput     = [];
        unset($this->selectedDivision);
        unset($this->competitorRows);
    }

    // ─── Computed ────────────────────────────────────────────────────────────

    #[Computed]
    public function competitorRows(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();

        $division = $this->selectedDivision;
        $filter   = $division?->competitionEvent?->division_filter ?? '';
        $dayId    = $this->divisionDayId();

        $eeCollection = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
            ->with(['enrolment.competitor', 'enrolment.rank', 'result'])
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
            ->values();
    }

    // ─── Bracket ─────────────────────────────────────────────────────────────

    public function generateBracket(): void
    {
        if (! $this->division_id) return;

        if (RoundRobinMatch::where('division_id', $this->division_id)->exists()) {
            Notification::make()->title('Bracket already generated.')->warning()->send();
            $this->bracketExists = true;
            return;
        }

        $dayId = $this->divisionDayId();
        $competitors = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
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
                    return ['ee_id' => $ee->id, 'name' => $this->resolveEeName($ee), 'info' => $this->buildPairingInfo($ee, $filter)];
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
        if ($division?->status !== 'running') {
            $updateData['status'] = 'running';
            if (! $division?->actual_start_at) {
                $updateData['actual_start_at'] = now();
            }
        }
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
            $this->dispatch('cancel-scoring-requested');
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

        $dayId = $this->divisionDayId();
        $competitors = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
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
        if ($division?->status !== 'running') {
            $updateData['status'] = 'running';
            if (! $division?->actual_start_at) {
                $updateData['actual_start_at'] = now();
            }
        }
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
        $this->dispatch('bracket-match-recorded');

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
        $this->dispatch('bracket-match-recorded');

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
        $this->dispatch('bracket-match-recorded');

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
        MatchPenalty::whereIn('result_id', $resultIds)->whereIn('type', ['dq', 'forfeit'])->delete();

        $match->update(['home_result' => null]);
        unset($this->bracketScoreInput[$matchId]);
        $this->applyBracketPlacements();
        $this->dispatch('timer-reset', matchId: $matchId);
        $this->dispatch('bracket-match-recorded');
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

        Division::find($this->division_id)?->update(['status' => 'assigned', 'actual_start_at' => null]);

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
        foreach (MatchPenalty::whereIn('round_robin_match_id', $matchIds)->whereIn('type', ['dq', 'forfeit'])->get() as $p) {
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
            $winner  = $m->winnerId();
            $canUndo = false;
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
                'round'                 => $m->round,
                'bracket'               => $m->bracket,
                'home_id'               => $m->home_enrolment_event_id,
                'away_id'               => $m->away_enrolment_event_id,
                'home_name'             => $eeNames[$m->home_enrolment_event_id] ?? 'TBD',
                'away_name'             => $eeNames[$m->away_enrolment_event_id] ?? 'TBD',
                'home_info'             => $eeInfo[$m->home_enrolment_event_id]  ?? '',
                'away_info'             => $eeInfo[$m->away_enrolment_event_id]  ?? '',
                'home_result'           => $m->home_result,
                'home_score'            => $m->home_score,
                'away_score'            => $m->away_score,
                'winner_id'             => $winner,
                'is_pending'            => $m->isPending(),
                'is_bye'                => $m->isBye(),
                'can_undo'              => $canUndo,
                'home_dq_in_match'      => in_array('dq', $homeTypesInMatch),
                'home_forfeit_in_match' => in_array('forfeit', $homeTypesInMatch),
                'away_dq_in_match'      => in_array('dq', $awayTypesInMatch),
                'away_forfeit_in_match' => in_array('forfeit', $awayTypesInMatch),
            ];
        }

        return $map;
    }

    public function isScoringComplete(): bool
    {
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

    public function getCompetitorCount(): int
    {
        $dayId = $this->divisionDayId();
        return EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
            ->count();
    }

    // ─── Penalty / DQ ────────────────────────────────────────────────────────

    public function openPenaltyModal(int $resultId, string $type, int $matchId = 0): void
    {
        $this->dispatch('open-penalty-modal', resultId: $resultId, type: $type, matchId: $matchId ?: null);
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
            $this->applyBracketPlacements();
        }
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
        $this->applyBracketPlacements();
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function handleDqAutoAdvance(Result $result): void
    {
        if (! $result->disqualified && ! $result->forfeited) return;
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

        if ($event?->bracket_prefer_different_dojo)  $sorted = $this->applyPreferDifferentDojo($sorted);
        if ($event?->bracket_avoid_repeat_matchups)  $sorted = $this->applyAvoidRepeatMatchups($sorted);

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
