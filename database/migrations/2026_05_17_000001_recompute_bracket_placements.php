<?php

use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Recompute placements for every division that has bracket match data.
        // This corrects stale placements left by the applyBracketPlacements() bug
        // where intermediate match states accumulated and were never cleared.
        $divisionIds = RoundRobinMatch::distinct()->pluck('division_id');

        foreach ($divisionIds as $divisionId) {
            $division = Division::with('competitionEvent')->find($divisionId);
            if (! $division) continue;

            $format = $division->competitionEvent->effectiveTournamentFormat();

            // Reset all non-overridden placements for this division.
            $eeIds = EnrolmentEvent::where('division_id', $divisionId)
                ->where('removed', false)
                ->pluck('id');

            Result::whereIn('enrolment_event_id', $eeIds)
                ->where('placement_overridden', false)
                ->whereNotNull('placement')
                ->update(['placement' => null]);

            // Only matches with a recorded result drive placement.
            $matches = RoundRobinMatch::where('division_id', $divisionId)
                ->whereNotNull('home_result')
                ->get();

            if ($matches->isEmpty()) continue;

            if ($format === 'round_robin') {
                $winCounts   = $eeIds->mapWithKeys(fn ($id) => [$id => 0])->toArray();
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
                    $this->setPlacement((int) $eeId, $rank);
                    $prevWins = $wins;
                    $countAtRank++;
                }
            } elseif ($format === 'se_3rd_place') {
                $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
                $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
                if ($wbFinal?->winnerId()) {
                    $this->setPlacement($wbFinal->winnerId(), 1);
                    if ($wbFinal->loserId()) $this->setPlacement($wbFinal->loserId(), 2);
                }
                $repFinal = $matches->where('bracket', 'repechage')->sortByDesc('round')->first();
                if ($repFinal?->winnerId()) {
                    $this->setPlacement($repFinal->winnerId(), 3);
                    if ($repFinal->loserId()) $this->setPlacement($repFinal->loserId(), 4);
                } elseif ($wbFinalRound >= 2) {
                    foreach ($matches->where('bracket', 'winners')->where('round', $wbFinalRound - 1) as $semi) {
                        if ($semi->loserId()) $this->setPlacement($semi->loserId(), 3);
                    }
                }
            } elseif ($format === 'double_elimination') {
                $gf = $matches->firstWhere('bracket', 'grand_final');
                if ($gf?->winnerId()) {
                    $this->setPlacement($gf->winnerId(), 1);
                    if ($gf->loserId()) $this->setPlacement($gf->loserId(), 2);
                }
            } elseif ($format === 'repechage') {
                $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
                $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
                if ($wbFinal?->winnerId()) {
                    $this->setPlacement($wbFinal->winnerId(), 1);
                    if ($wbFinal->loserId()) $this->setPlacement($wbFinal->loserId(), 2);
                }
                $repMatches  = $matches->where('bracket', 'repechage');
                $maxRepRound = $repMatches->max('round');
                $repFinal    = $repMatches->where('round', $maxRepRound)->first();
                if ($repFinal?->winnerId()) {
                    $this->setPlacement($repFinal->winnerId(), 3);
                    if ($repFinal->loserId()) $this->setPlacement($repFinal->loserId(), 4);
                }
            } else {
                // Single elimination — both semi-final losers share 3rd.
                $wbFinalRound = $matches->where('bracket', 'winners')->max('round');
                $wbFinal      = $matches->where('bracket', 'winners')->where('round', $wbFinalRound)->first();
                if ($wbFinal?->winnerId()) {
                    $this->setPlacement($wbFinal->winnerId(), 1);
                    if ($wbFinal->loserId()) $this->setPlacement($wbFinal->loserId(), 2);
                }
                if ($wbFinalRound >= 2) {
                    foreach ($matches->where('bracket', 'winners')->where('round', $wbFinalRound - 1) as $semi) {
                        if ($semi->loserId()) $this->setPlacement($semi->loserId(), 3);
                    }
                }
            }
        }
    }

    private function setPlacement(int $eeId, int $placement): void
    {
        $ee = EnrolmentEvent::find($eeId);
        if (! $ee) return;

        $result = Result::where('enrolment_event_id', $ee->id)->first();
        if (! $result || $result->placement_overridden) return;

        $result->forceFill(['placement' => $placement])->save();
    }

    public function down(): void
    {
        // Not reversible — placements were corrupt before this migration.
    }
};
