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
            ['division_id' => $ee->division_id]
        );
    }

    public function submitJudgeScore(Result $result, int $judgeNumber, float $score, bool $isTiebreaker = false): void
    {
        DB::transaction(function () use ($result, $judgeNumber, $score, $isTiebreaker) {
            JudgeScore::updateOrCreate(
                ['result_id' => $result->id, 'judge_number' => $judgeNumber, 'is_tiebreaker' => $isTiebreaker],
                ['score' => $score]
            );

            if (! $isTiebreaker) {
                $scores   = $result->judgeScores()->where('is_tiebreaker', false)->get();
                $method   = $result->enrolmentEvent->competitionEvent->effectiveScoringMethod();
                $count    = $scores->count();
                $sum      = $scores->sum('score');
                $computed = ($method === 'judges_average' && $count > 0) ? round($sum / $count, 3) : $sum;
                $result->update(['total_score' => $computed]);

                if ($result->division_id) {
                    $this->autoRankDivision(Division::find($result->division_id));
                }
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
     * Placements are written in a single batched upsert to avoid N individual UPDATEs.
     */
    public function autoRankDivision(Division $division): void
    {
        $results = Result::where('division_id', $division->id)
            ->where('disqualified', false)
            ->get();

        $method  = $division->competitionEvent->effectiveScoringMethod();
        $updates = [];
        $now     = now();

        if (in_array($method, ['judges_total', 'judges_average', 'first_to_n'])) {
            $ranked    = $results->sortByDesc(fn ($r) => [$r->total_score ?? PHP_INT_MIN, $r->tiebreaker_score ?? PHP_INT_MIN])->values();
            $place     = 1;
            $prevScore = null;
            $prevTb    = null;
            $prevPlace = 1;
            foreach ($ranked as $i => $result) {
                if (! $result->placement_overridden) {
                    $sameAsPrev    = $i > 0
                        && (float) ($result->total_score ?? PHP_INT_MIN) === (float) ($prevScore ?? PHP_INT_MIN)
                        && (float) ($result->tiebreaker_score ?? PHP_INT_MIN) === (float) ($prevTb ?? PHP_INT_MIN);
                    $assignedPlace = $sameAsPrev ? $prevPlace : $place;
                    $updates[]     = ['id' => $result->id, 'placement' => $assignedPlace, 'updated_at' => $now];
                    $prevPlace     = $assignedPlace;
                }
                $prevScore = $result->total_score;
                $prevTb    = $result->tiebreaker_score;
                $place++;
            }
        } elseif ($method === 'win_loss') {
            $order  = ['win' => 0, 'draw' => 1, 'loss' => 2];
            $ranked = $results->sortBy(fn ($r) => $order[$r->win_loss] ?? 99)->values();
            $place  = 1;
            foreach ($ranked as $result) {
                if (! $result->placement_overridden) {
                    $updates[] = ['id' => $result->id, 'placement' => $place, 'updated_at' => $now];
                }
                $place++;
            }
        }

        if (! empty($updates)) {
            Result::upsert($updates, ['id'], ['placement', 'updated_at']);
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
            $result->judgeScores()->where('is_tiebreaker', true)->delete();
            $result->update(['tiebreaker_score' => null]);

            if ($result->division_id) {
                $this->autoRankDivision(Division::find($result->division_id));
            }
        });
    }

    public function overridePlacement(Result $result, int $placement): void
    {
        $result->forceFill(['placement' => $placement, 'placement_overridden' => true])->save();
    }

    public function clearPlacementOverride(Result $result): void
    {
        $result->forceFill(['placement_overridden' => false])->save();

        if ($result->division_id) {
            $this->autoRankDivision(Division::find($result->division_id));
        }
    }

    public function toggleDisqualify(Result $result): void
    {
        $result->forceFill(['disqualified' => ! $result->disqualified, 'placement' => null])->save();

        if ($result->division_id) {
            $this->autoRankDivision(Division::find($result->division_id));
        }
    }
}
