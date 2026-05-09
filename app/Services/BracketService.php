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

        $n = $sortedCompetitors->count();
        $bracketSize = 1;
        while ($bracketSize < $n) {
            $bracketSize *= 2;
        }

        $slot = 1;
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $home = $sortedCompetitors->get($i);
            $away = $sortedCompetitors->get($i + 1);

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
                $this->advance($match, $format);
            }
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

        $winnerId = $match->winnerId();
        $loserId  = $match->loserId();

        if (! $winnerId) return;
        if ($match->bracket === 'grand_final') return;

        $nextSlot  = (int) ceil($match->bracket_slot / 2);
        $nextRound = $match->round + 1;
        $isOdd     = ($match->bracket_slot % 2 === 1);

        $this->fillOrCreate($match->division_id, $nextRound, $match->bracket, $nextSlot, $winnerId, $isOdd);

        if ($format === 'double_elimination' && $loserId && $match->bracket === 'winners') {
            $this->sendToLosers($match->division_id, $match, $loserId);
        }

        if ($format === 'double_elimination') {
            $this->checkGrandFinal($match->division_id);
        }
    }

    private function sendToLosers(int $divisionId, RoundRobinMatch $wbMatch, int $loserId): void
    {
        if ($wbMatch->round === 1) {
            // WBR1 losers pair with each other in LBR1
            $lbSlot = (int) ceil($wbMatch->bracket_slot / 2);
            $isOdd  = ($wbMatch->bracket_slot % 2 === 1);
            $this->fillOrCreate($divisionId, 1, 'losers', $lbSlot, $loserId, $isOdd);
        } else {
            // WBRn (n≥2) losers enter LB at round 2*(n-1), playing LBR survivor
            $lbRound = 2 * ($wbMatch->round - 1);
            $lbSlot  = $wbMatch->bracket_slot;
            $this->fillOrCreate($divisionId, $lbRound, 'losers', $lbSlot, $loserId, false);
        }
    }

    private function fillOrCreate(int $divisionId, int $round, string $bracket, int $slot, int $eeId, bool $isHome): void
    {
        $match = RoundRobinMatch::where('division_id', $divisionId)
            ->where('round', $round)
            ->where('bracket', $bracket)
            ->where('bracket_slot', $slot)
            ->first();

        if (! $match) {
            RoundRobinMatch::create([
                'division_id'             => $divisionId,
                'home_enrolment_event_id' => $isHome ? $eeId : null,
                'away_enrolment_event_id' => $isHome ? null : $eeId,
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
