<?php

namespace App\Services;

use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\Result;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    public function getOrCreateResult(EnrolmentEvent $ee): Result
    {
        return Result::firstOrCreate(
            ['enrolment_event_id' => $ee->id],
            ['division_id' => $ee->division_id, 'disqualified' => false, 'placement_overridden' => false]
        );
    }

    public function submitJudgeScore(Result $result, int $judgeNumber, float $score): void
    {
        DB::transaction(function () use ($result, $judgeNumber, $score) {
            JudgeScore::updateOrCreate(
                ['result_id' => $result->id, 'judge_number' => $judgeNumber],
                ['score' => $score]
            );

            $scores  = $result->judgeScores()->get();
            $method  = $result->enrolmentEvent->competitionEvent->effectiveScoringMethod();
            $count   = $scores->count();
            $sum     = $scores->sum('score');
            $computed = ($method === 'judges_average' && $count > 0) ? round($sum / $count, 3) : $sum;
            $result->update(['total_score' => $computed]);

            if ($result->division_id) {
                $this->autoRankDivision(Division::find($result->division_id));
            }
        });
    }

    public function recordWinLoss(Result $result, string $winLoss): void
    {
        DB::transaction(function () use ($result, $winLoss) {
            $result->update(['win_loss' => $winLoss]);

            if ($result->division_id) {
                $this->autoRankDivision(Division::find($result->division_id));
            }
        });
    }

    public function recordPoints(Result $result, int $points): void
    {
        DB::transaction(function () use ($result, $points) {
            $result->update(['total_score' => $points]);

            if ($result->division_id) {
                $this->autoRankDivision(Division::find($result->division_id));
            }
        });
    }

    /**
     * Auto-rank all non-disqualified, non-overridden results in a division.
     * Overridden placements are kept but non-overridden ones are re-calculated.
     */
    public function autoRankDivision(Division $division): void
    {
        $results = Result::where('division_id', $division->id)
            ->where('disqualified', false)
            ->get();

        $method = $division->competitionEvent->effectiveScoringMethod();

        if (in_array($method, ['judges_total', 'judges_average', 'first_to_n'])) {
            // Primary: total_score DESC; secondary: tiebreaker_score DESC (null = not yet run)
            $ranked = $results
                ->sortByDesc(fn ($r) => [
                    $r->total_score      ?? -999,
                    $r->tiebreaker_score ?? -999,
                ])
                ->values();

            // Assign placements, giving tied competitors the same placement number
            $place    = 1;
            $prevScore = null;
            $prevTb    = null;
            $prevPlace = 1;
            foreach ($ranked as $i => $result) {
                if (! $result->placement_overridden) {
                    $sameAsPrev = $i > 0
                        && (float) ($result->total_score ?? -999) === (float) ($prevScore ?? -999)
                        && (float) ($result->tiebreaker_score ?? -999) === (float) ($prevTb ?? -999);

                    $assignedPlace = $sameAsPrev ? $prevPlace : $place;
                    $result->update(['placement' => $assignedPlace]);
                    $prevPlace = $assignedPlace;
                }
                $prevScore = $result->total_score;
                $prevTb    = $result->tiebreaker_score;
                $place++;
            }
        } elseif ($method === 'win_loss') {
            // wins first, draws second, losses last
            $order = ['win' => 0, 'draw' => 1, 'loss' => 2];
            $ranked = $results
                ->sortBy(fn ($r) => $order[$r->win_loss] ?? 99)
                ->values();

            $place = 1;
            foreach ($ranked as $result) {
                if (! $result->placement_overridden) {
                    $result->update(['placement' => $place]);
                }
                $place++;
            }
        }
    }

    public function saveTiebreakerScore(Result $result, float $score): void
    {
        DB::transaction(function () use ($result, $score) {
            $result->update(['tiebreaker_score' => $score]);

            if ($result->division_id) {
                $this->autoRankDivision(Division::find($result->division_id));
            }
        });
    }

    public function clearTiebreakerScore(Result $result): void
    {
        DB::transaction(function () use ($result) {
            $result->update(['tiebreaker_score' => null]);

            if ($result->division_id) {
                $this->autoRankDivision(Division::find($result->division_id));
            }
        });
    }

    public function overridePlacement(Result $result, int $placement): void
    {
        $result->update(['placement' => $placement, 'placement_overridden' => true]);
    }

    public function clearPlacementOverride(Result $result): void
    {
        $result->update(['placement_overridden' => false]);

        if ($result->division_id) {
            $this->autoRankDivision(Division::find($result->division_id));
        }
    }

    public function toggleDisqualify(Result $result): void
    {
        $result->update(['disqualified' => ! $result->disqualified, 'placement' => null]);

        if ($result->division_id) {
            $this->autoRankDivision(Division::find($result->division_id));
        }
    }
}
