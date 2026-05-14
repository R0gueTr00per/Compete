<?php

namespace App\Services;

use App\Models\Division;
use App\Models\RoundRobinMatch;
use Illuminate\Support\Collection;

class BracketService
{
    /**
     * Generate round-1 matches for a division.
     * $sortedCompetitors: Collection of EnrolmentEvent sorted by display name.
     */
    public function generate(Division $division, Collection $sortedCompetitors): void
    {
        $division->loadMissing('competitionEvent');
        $format = $division->competitionEvent->effectiveTournamentFormat();

        if ($format === 'round_robin') {
            $this->generateRoundRobin($division, $sortedCompetitors);
            return;
        }

        $n = $sortedCompetitors->count();
        $bracketSize = 1;
        while ($bracketSize < $n) {
            $bracketSize *= 2;
        }

        // Pass 1: create all R1 matches so sibling-existence checks work during Pass 2.
        $byeMatches = [];
        $slot = 1;
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $home = $sortedCompetitors->get($i);
            $away = $sortedCompetitors->get($i + 1);

            if (! $home) {
                // Double-bye slot — skip but keep the slot counter in sync.
                $slot++;
                continue;
            }

            $match = RoundRobinMatch::create([
                'division_id'             => $division->id,
                'home_enrolment_event_id' => $home->id,
                'away_enrolment_event_id' => $away?->id,
                'home_result'             => $away === null ? 'win' : null,
                'round'                   => 1,
                'bracket'                 => 'winners',
                'bracket_slot'            => $slot++,
            ]);

            if ($away === null) {
                $byeMatches[] = $match;
            }
        }

        // Pass 2: advance BYE winners now that all R1 matches exist in the DB.
        foreach ($byeMatches as $match) {
            $this->advance($match->fresh(), $format);
        }
    }

    /**
     * Advance winner (and send loser to losers bracket for DE) after a match result is recorded.
     * Pass $format explicitly when already known to avoid an extra query.
     */
    public function advance(RoundRobinMatch $match, ?string $format = null): void
    {
        if ($format === null) {
            $match->loadMissing('division.competitionEvent');
            $format = $match->division->competitionEvent->effectiveTournamentFormat();
        }

        if ($format === 'round_robin') return;

        $winnerId = $match->winnerId();
        $loserId  = $match->loserId();

        if (! $winnerId) return;
        if ($match->bracket === 'grand_final') return;

        // ── Repechage bracket (mini single-elimination for 3rd place) ────────────
        if ($match->bracket === 'repechage') {
            $repR1Count  = RoundRobinMatch::where('division_id', $match->division_id)
                ->where('bracket', 'repechage')
                ->where('round', 1)
                ->count();
            $maxRepRound = $repR1Count > 1 ? (int) ceil(log($repR1Count, 2)) + 1 : 1;
            $nextRound   = $match->round + 1;

            if ($nextRound > $maxRepRound) return;

            $nextSlot = (int) ceil($match->bracket_slot / 2);
            $isOdd    = ($match->bracket_slot % 2 === 1);
            $nextMatch = $this->fillOrCreate($match->division_id, $nextRound, 'repechage', $nextSlot, $winnerId, $isOdd);

            $nextMatch->refresh();
            if (
                $nextMatch->home_enrolment_event_id !== null
                && $nextMatch->away_enrolment_event_id === null
                && $nextMatch->home_result === null
            ) {
                $depth     = (int) 2 ** ($nextRound - 1);
                $slotFrom  = ($nextSlot - 1) * $depth + 1;
                $slotTo    = $nextSlot * $depth;
                $ancestors = RoundRobinMatch::where('division_id', $match->division_id)
                    ->where('bracket', 'repechage')
                    ->where('round', 1)
                    ->whereBetween('bracket_slot', [$slotFrom, $slotTo])
                    ->count();

                if ($ancestors === 1) {
                    $nextMatch->update(['home_result' => 'win']);
                    $this->advance($nextMatch->fresh(), $format);
                }
            }
            return;
        }

        $r1Count    = RoundRobinMatch::where('division_id', $match->division_id)
            ->where('bracket', 'winners')
            ->where('round', 1)
            ->count();
        $maxWbRound = $r1Count > 1 ? (int) ceil(log($r1Count, 2)) + 1 : 1;
        // LB has 2*(WB rounds - 1) rounds: odd rounds keep slot (face incoming WB loser next round),
        // even rounds merge (pairs of LB survivors compete).
        $maxLbRound = 2 * ($maxWbRound - 1);

        $nextRound = $match->round + 1;

        // ── Losers bracket ──────────────────────────────────────────────────────
        if ($match->bracket === 'losers') {
            // Odd LB rounds keep slot (1-to-1 with the WB loser arriving in the next even round).
            // Even LB rounds merge pairs (ceil).
            $nextSlot = ($match->round % 2 === 1)
                ? $match->bracket_slot
                : (int) ceil($match->bracket_slot / 2);

            if ($nextRound > $maxLbRound) {
                if ($format === 'double_elimination') {
                    $this->checkGrandFinal($match->division_id);
                }
                return;
            }

            $this->fillOrCreate($match->division_id, $nextRound, 'losers', $nextSlot, $winnerId, true);

            if ($format === 'double_elimination') {
                $this->checkGrandFinal($match->division_id);
            }
            return;
        }

        // ── Winners bracket ──────────────────────────────────────────────────────
        $nextSlot = (int) ceil($match->bracket_slot / 2);
        $isOdd    = ($match->bracket_slot % 2 === 1);

        // WB is over — in DE still handle the loser path and check for grand final.
        if ($nextRound > $maxWbRound) {
            if ($format === 'double_elimination' && $loserId) {
                $this->sendToLosers($match->division_id, $match, $loserId, $format);
            }
            if ($format === 'double_elimination') {
                $this->checkGrandFinal($match->division_id);
            }
            if ($format === 'se_3rd_place') {
                $this->checkThirdPlace($match->division_id);
            }
            return;
        }

        $nextMatch = $this->fillOrCreate($match->division_id, $nextRound, 'winners', $nextSlot, $winnerId, $isOdd);

        if ($format === 'double_elimination' && $loserId) {
            $this->sendToLosers($match->division_id, $match, $loserId, $format);
        }

        if ($format === 'double_elimination') {
            $this->checkGrandFinal($match->division_id);
        }

        if ($format === 'repechage') {
            $this->checkRepechage($match->division_id);
        }

        if ($format === 'se_3rd_place') {
            $this->checkThirdPlace($match->division_id);
        }

        // Chain BYE: if the next WB match can only ever receive one competitor
        // (all R1 ancestor slots that feed it are just one), auto-win and keep advancing.
        $nextMatch->refresh();
        if (
            $nextMatch->home_enrolment_event_id !== null
            && $nextMatch->away_enrolment_event_id === null
            && $nextMatch->home_result === null
        ) {
            $depth     = (int) 2 ** ($nextRound - 1);
            $slotFrom  = ($nextSlot - 1) * $depth + 1;
            $slotTo    = $nextSlot * $depth;
            $ancestors = RoundRobinMatch::where('division_id', $match->division_id)
                ->where('bracket', 'winners')
                ->where('round', 1)
                ->whereBetween('bracket_slot', [$slotFrom, $slotTo])
                ->count();

            if ($ancestors === 1) {
                $nextMatch->update(['home_result' => 'win']);
                $this->advance($nextMatch->fresh(), $format);
            }
        }
    }

    private function sendToLosers(int $divisionId, RoundRobinMatch $wbMatch, int $loserId, ?string $format = null): void
    {
        if ($wbMatch->round === 1) {
            // WBR1 losers pair with each other in LBR1
            $lbSlot  = (int) ceil($wbMatch->bracket_slot / 2);
            $isOdd   = ($wbMatch->bracket_slot % 2 === 1);
            $lbMatch = $this->fillOrCreate($divisionId, 1, 'losers', $lbSlot, $loserId, $isOdd);

            // If the partner WB R1 slot is a BYE or missing, no loser will ever fill the other
            // side of this LB match — auto-win and advance the lone competitor.
            $partnerSlot          = $isOdd ? $wbMatch->bracket_slot + 1 : $wbMatch->bracket_slot - 1;
            $partnerProducesLoser = RoundRobinMatch::where('division_id', $divisionId)
                ->where('bracket', 'winners')
                ->where('round', 1)
                ->where('bracket_slot', $partnerSlot)
                ->whereNotNull('away_enrolment_event_id')
                ->exists();

            if (
                ! $partnerProducesLoser
                && $lbMatch->home_enrolment_event_id !== null
                && $lbMatch->away_enrolment_event_id === null
                && $lbMatch->home_result === null
            ) {
                $lbMatch->update(['home_result' => 'win']);
                $this->advance($lbMatch->fresh(), $format);
            }
        } else {
            // WBRn (n≥2) losers enter LB at round 2*(n-1), playing LBR survivor
            $lbRound = 2 * ($wbMatch->round - 1);
            $lbSlot  = $wbMatch->bracket_slot;
            $this->fillOrCreate($divisionId, $lbRound, 'losers', $lbSlot, $loserId, false);
        }
    }

    private function fillOrCreate(int $divisionId, int $round, string $bracket, int $slot, int $eeId, bool $isHome): RoundRobinMatch
    {
        $match = RoundRobinMatch::where('division_id', $divisionId)
            ->where('round', $round)
            ->where('bracket', $bracket)
            ->where('bracket_slot', $slot)
            ->first();

        if (! $match) {
            // First arrival always goes as home (home_enrolment_event_id is NOT NULL).
            $match = RoundRobinMatch::create([
                'division_id'             => $divisionId,
                'home_enrolment_event_id' => $eeId,
                'away_enrolment_event_id' => null,
                'home_result'             => null,
                'round'                   => $round,
                'bracket'                 => $bracket,
                'bracket_slot'            => $slot,
            ]);
        } else {
            if ($match->home_enrolment_event_id === null) {
                $match->update(['home_enrolment_event_id' => $eeId]);
            } elseif ($match->away_enrolment_event_id === null) {
                $match->update(['away_enrolment_event_id' => $eeId]);
            }
        }

        return $match->fresh();
    }

    private function generateRoundRobin(Division $division, Collection $sortedCompetitors): void
    {
        $positions = $sortedCompetitors->values()->all();

        if (count($positions) % 2 !== 0) {
            $positions[] = null; // BYE placeholder so each round has even pairs
        }

        $total = count($positions);

        for ($round = 1; $round <= $total - 1; $round++) {
            $slot = 1;
            for ($i = 0; $i < $total / 2; $i++) {
                $home = $positions[$i];
                $away = $positions[$total - 1 - $i];

                if ($home !== null && $away !== null) {
                    RoundRobinMatch::create([
                        'division_id'             => $division->id,
                        'home_enrolment_event_id' => $home->id,
                        'away_enrolment_event_id' => $away->id,
                        'home_result'             => null,
                        'round'                   => $round,
                        'bracket'                 => 'winners',
                        'bracket_slot'            => $slot,
                    ]);
                    $slot++;
                }
            }

            // Rotate: keep position 0 fixed, rotate positions 1..total-1
            $last = array_pop($positions);
            array_splice($positions, 1, 0, [$last]);
        }
    }

    private function checkRepechage(int $divisionId): void
    {
        if (RoundRobinMatch::where('division_id', $divisionId)->where('bracket', 'repechage')->exists()) {
            return;
        }

        $wbMatches  = RoundRobinMatch::where('division_id', $divisionId)->where('bracket', 'winners')->get();
        $maxWbRound = $wbMatches->max('round');
        if (! $maxWbRound) return;

        $wbFinal = $wbMatches->where('round', $maxWbRound)->first();
        if (! $wbFinal) return;

        // Both finalists must be seeded before we can build the repechage bracket.
        if (! $wbFinal->home_enrolment_event_id || ! $wbFinal->away_enrolment_event_id) return;

        // Collect every competitor who lost to either finalist throughout the bracket.
        $candidates = collect();
        foreach ([$wbFinal->home_enrolment_event_id, $wbFinal->away_enrolment_event_id] as $finalistId) {
            $wbMatches->each(function (RoundRobinMatch $m) use ($finalistId, &$candidates) {
                if ($m->winnerId() === $finalistId && $m->loserId()) {
                    $candidates->push((object) ['id' => $m->loserId(), 'round' => $m->round]);
                }
            });
        }

        $candidates  = $candidates->unique('id')->sortByDesc('round')->values();
        $n           = $candidates->count();

        if ($n === 0) return;

        if ($n === 1) {
            // Sole candidate gets a BYE — they auto-win 3rd place.
            RoundRobinMatch::create([
                'division_id'             => $divisionId,
                'home_enrolment_event_id' => $candidates->first()->id,
                'away_enrolment_event_id' => null,
                'home_result'             => 'win',
                'round'                   => 1,
                'bracket'                 => 'repechage',
                'bracket_slot'            => 1,
            ]);
            return;
        }

        // Build a mini single-elimination repechage bracket.
        $bracketSize = 1;
        while ($bracketSize < $n) {
            $bracketSize *= 2;
        }

        $byeMatches = [];
        $slot       = 1;
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $home = $candidates->get($i);
            $away = $candidates->get($i + 1);

            if (! $home) { $slot++; continue; }

            $match = RoundRobinMatch::create([
                'division_id'             => $divisionId,
                'home_enrolment_event_id' => $home->id,
                'away_enrolment_event_id' => $away?->id,
                'home_result'             => $away === null ? 'win' : null,
                'round'                   => 1,
                'bracket'                 => 'repechage',
                'bracket_slot'            => $slot++,
            ]);

            if ($away === null) {
                $byeMatches[] = $match;
            }
        }

        foreach ($byeMatches as $m) {
            $this->advance($m->fresh(), 'repechage');
        }
    }

    private function checkThirdPlace(int $divisionId): void
    {
        $r1Count    = RoundRobinMatch::where('division_id', $divisionId)->where('bracket', 'winners')->where('round', 1)->count();
        $maxWbRound = $r1Count > 1 ? (int) ceil(log($r1Count, 2)) + 1 : 1;

        if ($maxWbRound < 2) return;

        $semiFinalRound = $maxWbRound - 1;

        $semis = RoundRobinMatch::where('division_id', $divisionId)
            ->where('bracket', 'winners')
            ->where('round', $semiFinalRound)
            ->orderBy('bracket_slot')
            ->get();

        // Only proceed if there are 2 real (non-bye) semi-finals — a bye semi produces
        // no loser, so with only 1 real semi the lone loser gets 3rd via placement fallback.
        $realSemis = $semis->filter(fn ($m) => $m->away_enrolment_event_id !== null);
        if ($realSemis->count() < 2) return;

        $semiLosers = $realSemis->map(fn ($m) => $m->loserId())->filter()->values();

        if ($semiLosers->isEmpty()) return;

        // Create or update the 3rd place match as each semi-final loser becomes known
        $existing = RoundRobinMatch::where('division_id', $divisionId)
            ->where('bracket', 'repechage')
            ->where('bracket_slot', 1)
            ->first();

        if (! $existing) {
            RoundRobinMatch::create([
                'division_id'             => $divisionId,
                'home_enrolment_event_id' => $semiLosers[0],
                'away_enrolment_event_id' => $semiLosers->get(1),
                'home_result'             => null,
                'round'                   => 1,
                'bracket'                 => 'repechage',
                'bracket_slot'            => 1,
            ]);
        } elseif ($existing->away_enrolment_event_id === null && $semiLosers->count() >= 2) {
            // Second semi-final just finished — fill in the away slot
            $newLoser = $semiLosers->first(fn ($id) => $id !== $existing->home_enrolment_event_id);
            if ($newLoser) {
                $existing->update(['away_enrolment_event_id' => $newLoser]);
            }
        }
    }

    private function checkGrandFinal(int $divisionId): void
    {
        if (RoundRobinMatch::where('division_id', $divisionId)->where('bracket', 'grand_final')->exists()) {
            return;
        }

        $pending = RoundRobinMatch::where('division_id', $divisionId)
            ->whereIn('bracket', ['winners', 'losers'])
            ->whereNull('home_result')
            ->count();

        if ($pending > 0) return;

        $wbWinner = RoundRobinMatch::where('division_id', $divisionId)
            ->where('bracket', 'winners')
            ->orderByDesc('round')
            ->first()?->winnerId();

        $lbWinner = RoundRobinMatch::where('division_id', $divisionId)
            ->where('bracket', 'losers')
            ->orderByDesc('round')
            ->first()?->winnerId();

        if (! $wbWinner || ! $lbWinner) return;

        RoundRobinMatch::create([
            'division_id'             => $divisionId,
            'home_enrolment_event_id' => $wbWinner,
            'away_enrolment_event_id' => $lbWinner,
            'home_result'             => null,
            'round'                   => 1,
            'bracket'                 => 'grand_final',
            'bracket_slot'            => 1,
        ]);
    }
}
