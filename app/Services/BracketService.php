<?php

namespace App\Services;

use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use Illuminate\Support\Collection;

class BracketService
{
    private array $r1CountCache = [];

    private function getR1Count(int $divisionId, string $bracket): int
    {
        $key = "{$divisionId}_{$bracket}";
        if (! isset($this->r1CountCache[$key])) {
            $this->r1CountCache[$key] = RoundRobinMatch::where('division_id', $divisionId)
                ->where('bracket', $bracket)
                ->where('round', 1)
                ->count();
        }
        return $this->r1CountCache[$key];
    }

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

        // Build a padded competitor list so no one ever gets more than one bye.
        // realMatchCount = max(0, n - bracketSize/2) pairs must play in R1.
        // Remaining competitors get single-bye slots interleaved with real-match
        // slots so every R2 feeder slot has exactly two R1 ancestors.
        $halfBracket    = $bracketSize / 2;
        $realMatchCount = max(0, $n - $halfBracket);
        $byeComps       = $sortedCompetitors->slice(0, $n - 2 * $realMatchCount)->values();
        $playComps      = $sortedCompetitors->slice($n - 2 * $realMatchCount)->values();

        $padded  = [];
        $byeIdx  = 0;
        $playIdx = 0;
        $r2Pairs = (int) ceil($halfBracket / 2);

        for ($pair = 0; $pair < $r2Pairs; $pair++) {
            // Slot A of this R2-feeder pair — prefer a bye competitor so they
            // share a R2 slot with a real-match winner (each person ≤ 1 bye).
            if ($byeIdx < $byeComps->count()) {
                $padded[] = $byeComps->get($byeIdx++);
                $padded[] = null;
            } elseif ($playIdx + 1 < $playComps->count()) {
                $padded[] = $playComps->get($playIdx++);
                $padded[] = $playComps->get($playIdx++);
            } else {
                $padded[] = null; $padded[] = null;
            }

            // Slot B of this R2-feeder pair — prefer a real match pair.
            if ($playIdx + 1 < $playComps->count()) {
                $padded[] = $playComps->get($playIdx++);
                $padded[] = $playComps->get($playIdx++);
            } elseif ($byeIdx < $byeComps->count()) {
                $padded[] = $byeComps->get($byeIdx++);
                $padded[] = null;
            } else {
                $padded[] = null; $padded[] = null;
            }
        }

        // Pass 1: create all R1 matches so sibling-existence checks work during Pass 2.
        $byeMatches = [];
        $slot = 1;
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $home = $padded[$i] ?? null;
            $away = $padded[$i + 1] ?? null;

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
            $format = $match->division->tournament_format
                ?? $match->division->competitionEvent->effectiveTournamentFormat();
        }

        if ($format === 'round_robin') return;

        $winnerId = $match->winnerId();
        $loserId  = $match->loserId();

        if (! $winnerId) return;
        if ($match->bracket === 'grand_final') return;

        // ── Repechage brackets (se_3rd_place uses 'repechage'; repechage format uses 'repechage_a'/'repechage_b') ──
        if (in_array($match->bracket, ['repechage', 'repechage_a', 'repechage_b'])) {
            $bracket     = $match->bracket;
            $repR1Count  = $this->getR1Count($match->division_id, $bracket);
            $maxRepRound = $repR1Count > 1 ? (int) ceil(log($repR1Count, 2)) + 1 : 1;
            $nextRound   = $match->round + 1;

            if ($nextRound > $maxRepRound) return;

            $nextSlot  = (int) ceil($match->bracket_slot / 2);
            $isOdd     = ($match->bracket_slot % 2 === 1);
            $nextMatch = $this->fillOrCreate($match->division_id, $nextRound, $bracket, $nextSlot, $winnerId, $isOdd);
            $this->resolveIfOpponentDqd($nextMatch->fresh(), $format);

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
                    ->where('bracket', $bracket)
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

        $r1Count    = $this->getR1Count($match->division_id, 'winners');
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

            $lbMatch = $this->fillOrCreate($match->division_id, $nextRound, 'losers', $nextSlot, $winnerId, true);
            $this->resolveIfOpponentDqd($lbMatch->fresh(), $format);

            // Odd LB rounds receive only LB survivors (no WB loser drops in). When the bracket
            // has an odd number of LB even-round matches, only one competitor reaches the next
            // odd-round slot — auto-win them so the bracket doesn't stall.
            if ($match->round % 2 === 0) {
                $lbMatch->refresh();
                if (
                    $lbMatch->home_enrolment_event_id !== null
                    && $lbMatch->away_enrolment_event_id === null
                    && $lbMatch->home_result === null
                ) {
                    $feederCount = RoundRobinMatch::where('division_id', $match->division_id)
                        ->where('bracket', 'losers')
                        ->where('round', $match->round)
                        ->whereIn('bracket_slot', [2 * $nextSlot - 1, 2 * $nextSlot])
                        ->count();
                    if ($feederCount <= 1) {
                        $lbMatch->update(['home_result' => 'win']);
                        $this->advance($lbMatch->fresh(), $format);
                    }
                }
            }

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
            if ($format === 'repechage') {
                $this->checkRepechage($match->division_id);
            }
            return;
        }

        $nextMatch = $this->fillOrCreate($match->division_id, $nextRound, 'winners', $nextSlot, $winnerId, $isOdd);
        $this->resolveIfOpponentDqd($nextMatch->fresh(), $format);

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

    /**
     * If both competitors are now seated and one is already DQ'd, resolve the match
     * immediately in favour of the non-DQ'd competitor and advance them.
     */
    private function resolveIfOpponentDqd(RoundRobinMatch $match, string $format): void
    {
        if (
            ! $match->home_enrolment_event_id
            || ! $match->away_enrolment_event_id
            || $match->home_result !== null
        ) {
            return;
        }

        $homeResult = Result::where('enrolment_event_id', $match->home_enrolment_event_id)->first();
        $awayResult = Result::where('enrolment_event_id', $match->away_enrolment_event_id)->first();
        $homeDq     = $homeResult && ($homeResult->disqualified || $homeResult->forfeited);
        $awayDq     = $awayResult && ($awayResult->disqualified || $awayResult->forfeited);

        if ($homeDq && ! $awayDq) {
            $match->update(['home_result' => 'loss']);
            $this->advance($match->fresh(), $format);
        } elseif ($awayDq && ! $homeDq) {
            $match->update(['home_result' => 'win']);
            $this->advance($match->fresh(), $format);
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
        if (RoundRobinMatch::where('division_id', $divisionId)->whereIn('bracket', ['repechage_a', 'repechage_b'])->exists()) {
            return;
        }

        $wbMatches  = RoundRobinMatch::where('division_id', $divisionId)->where('bracket', 'winners')->get();
        $r1Count    = $wbMatches->where('round', 1)->count();
        if (! $r1Count) return;
        $maxWbRound = $r1Count > 1 ? (int) ceil(log($r1Count, 2)) + 1 : 1;

        $wbFinal = $wbMatches->where('round', $maxWbRound)->first();
        if (! $wbFinal) return;

        // Both finalists must be seeded before we can build the repechage brackets.
        if (! $wbFinal->home_enrolment_event_id || ! $wbFinal->away_enrolment_event_id) return;

        // Build one mini-SE bracket per finalist: repechage_a for home, repechage_b for away.
        $sides = [
            'repechage_a' => $wbFinal->home_enrolment_event_id,
            'repechage_b' => $wbFinal->away_enrolment_event_id,
        ];

        foreach ($sides as $bracketName => $finalistId) {
            $candidates = collect();
            $wbMatches->each(function (RoundRobinMatch $m) use ($finalistId, &$candidates) {
                if ($m->winnerId() === $finalistId && $m->loserId()) {
                    $candidates->push((object) ['id' => $m->loserId(), 'round' => $m->round]);
                }
            });

            $candidates = $candidates->unique('id')->sortByDesc('round')->values();
            $n          = $candidates->count();

            if ($n === 0) continue;

            if ($n === 1) {
                RoundRobinMatch::create([
                    'division_id'             => $divisionId,
                    'home_enrolment_event_id' => $candidates->first()->id,
                    'away_enrolment_event_id' => null,
                    'home_result'             => 'win',
                    'round'                   => 1,
                    'bracket'                 => $bracketName,
                    'bracket_slot'            => 1,
                ]);
                continue;
            }

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
                    'bracket'                 => $bracketName,
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
    }

    private function checkThirdPlace(int $divisionId): void
    {
        $r1Count    = $this->getR1Count($divisionId, 'winners');
        $maxWbRound = $r1Count > 1 ? (int) ceil(log($r1Count, 2)) + 1 : 1;

        if ($maxWbRound < 2) return;

        $semiFinalRound = $maxWbRound - 1;

        $semis = RoundRobinMatch::where('division_id', $divisionId)
            ->where('bracket', 'winners')
            ->where('round', $semiFinalRound)
            ->orderBy('bracket_slot')
            ->get();

        // Filter to semi-finals that have a real opponent (not bye auto-wins).
        $realSemis = $semis->filter(fn ($m) => $m->away_enrolment_event_id !== null);

        if ($realSemis->isEmpty()) return;

        // With exactly 1 real semi, check whether the other slot is a genuine bye auto-win
        // (away=null, home_result='win') vs. just not yet filled. If it's only temporarily empty
        // (e.g. 7 competitors where the second semi is still pending), return and wait.
        // Also skip when semiFinalRound=1 (3 competitors): the lone semi loser gets 3rd via
        // the applyBracketPlacements fallback — no explicit match needed.
        if ($realSemis->count() === 1) {
            if ($semiFinalRound < 2) return;

            $hasCompletedByeSemi = $semis->contains(
                fn ($m) => $m->away_enrolment_event_id === null && $m->home_result === 'win'
            );
            if (! $hasCompletedByeSemi) return;

            $onlySemi    = $realSemis->first();
            $loneLoserId = $onlySemi->loserId();
            if (! $loneLoserId) return; // Semi not yet played

            $isDq = Result::where('enrolment_event_id', $loneLoserId)
                ->where(fn ($q) => $q->where('disqualified', true)->orWhere('forfeited', true))
                ->exists();
            if ($isDq) return; // DQ'd or forfeited → no 3rd place awarded

            $existing = RoundRobinMatch::where('division_id', $divisionId)
                ->where('bracket', 'repechage')
                ->where('bracket_slot', 1)
                ->first();

            if (! $existing) {
                RoundRobinMatch::create([
                    'division_id'             => $divisionId,
                    'home_enrolment_event_id' => $loneLoserId,
                    'away_enrolment_event_id' => null,
                    'home_result'             => 'win',
                    'round'                   => 1,
                    'bracket'                 => 'repechage',
                    'bracket_slot'            => 1,
                ]);
            }
            return;
        }

        $semiLosers = $realSemis->map(fn ($m) => $m->loserId())->filter()->values();

        if ($semiLosers->isEmpty()) return;

        // Exclude DQ'd/forfeited competitors — they should not play for 3rd place
        $dqEeIds = Result::whereIn('enrolment_event_id', $semiLosers->all())
            ->where(fn ($q) => $q->where('disqualified', true)->orWhere('forfeited', true))
            ->pluck('enrolment_event_id')
            ->toArray();
        $eligible = $semiLosers->reject(fn ($id) => in_array($id, $dqEeIds))->values();

        $existing = RoundRobinMatch::where('division_id', $divisionId)
            ->where('bracket', 'repechage')
            ->where('bracket_slot', 1)
            ->first();

        // The 3rd-place match has already been played — nothing to create or modify.
        if ($existing && $existing->home_result !== null) return;

        $bothSemisScored = $semiLosers->count() === 2;

        if ($bothSemisScored) {
            // Both semis done — resolve 3rd place now
            if ($eligible->isEmpty()) return; // Both losers DQ'd — no 3rd place match

            if ($eligible->count() === 1) {
                // One loser DQ'd — sole eligible loser wins 3rd automatically
                $soleLoserId = $eligible[0];
                if (! $existing) {
                    RoundRobinMatch::create([
                        'division_id'             => $divisionId,
                        'home_enrolment_event_id' => $soleLoserId,
                        'away_enrolment_event_id' => null,
                        'home_result'             => 'win',
                        'round'                   => 1,
                        'bracket'                 => 'repechage',
                        'bracket_slot'            => 1,
                    ]);
                } elseif ($existing->home_result === null) {
                    // Only modify if the match hasn't been played yet — never rewrite a completed result
                    $existing->update([
                        'home_enrolment_event_id' => $soleLoserId,
                        'away_enrolment_event_id' => null,
                        'home_result'             => 'win',
                    ]);
                }
                return;
            }

            // Both losers eligible — normal 3rd place match
            if (! $existing) {
                RoundRobinMatch::create([
                    'division_id'             => $divisionId,
                    'home_enrolment_event_id' => $eligible[0],
                    'away_enrolment_event_id' => $eligible->get(1),
                    'home_result'             => null,
                    'round'                   => 1,
                    'bracket'                 => 'repechage',
                    'bracket_slot'            => 1,
                ]);
            } elseif ($existing->away_enrolment_event_id === null) {
                $newLoser = $eligible->first(fn ($id) => $id !== $existing->home_enrolment_event_id);
                if ($newLoser) {
                    $existing->update(['away_enrolment_event_id' => $newLoser]);
                }
            }
        } else {
            // Only the first semi is done — seat the loser if eligible, wait for second semi
            if ($eligible->isEmpty()) return; // First loser is DQ'd — wait for second semi

            if (! $existing) {
                RoundRobinMatch::create([
                    'division_id'             => $divisionId,
                    'home_enrolment_event_id' => $eligible[0],
                    'away_enrolment_event_id' => null,
                    'home_result'             => null,
                    'round'                   => 1,
                    'bracket'                 => 'repechage',
                    'bracket_slot'            => 1,
                ]);
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
