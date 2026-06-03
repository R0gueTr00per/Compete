<?php

namespace App\Services;

use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\MatchPenalty;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use App\Models\ScoreEvent;
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

    public function addPoints(Result $result, float $amount): void
    {
        DB::transaction(function () use ($result, $amount) {
            $result->scoreEvents()->create(['amount' => $amount]);
            $total = $result->scoreEvents()->sum('amount');
            $result->update(['total_score' => $total]);

            if ($result->division_id) {
                $this->autoRankDivision(Division::find($result->division_id));
            }
        });
    }

    public function undoLastPoints(Result $result): void
    {
        DB::transaction(function () use ($result) {
            $last = $result->scoreEvents()->latest('created_at')->first();
            if ($last) {
                $last->delete();
            }
            $remaining = $result->scoreEvents()->count();
            $total     = $remaining > 0 ? $result->scoreEvents()->sum('amount') : null;
            $result->update(['total_score' => $total]);

            if ($result->division_id) {
                $this->autoRankDivision(Division::find($result->division_id));
            }
        });
    }

    public function recordPoints(Result $result, int $points): void
    {
        DB::transaction(function () use ($result, $points) {
            $result->scoreEvents()->delete();
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
        if ($division->placement_override_mode) {
            return;
        }

        $results = Result::where('division_id', $division->id)
            ->where('disqualified', false)
            ->get();

        $method  = $division->competitionEvent->effectiveScoringMethod();
        $updates = [];
        $now     = now();

        if (in_array($method, ['judges_total', 'judges_average', 'first_to_n', 'timed_points'])) {
            $ranked         = $results->sortByDesc(fn ($r) => [$r->total_score ?? PHP_INT_MIN, $r->tiebreaker_score ?? PHP_INT_MIN])->values();
            $place          = 1;
            $prevScore      = null;
            $prevTb         = null;
            $prevPlace      = 1;
            $prevOverridden = false;
            foreach ($ranked as $i => $result) {
                if (! $result->placement_overridden) {
                    $sameAsPrev    = $i > 0
                        && ! $prevOverridden
                        && (float) ($result->total_score ?? PHP_INT_MIN) === (float) ($prevScore ?? PHP_INT_MIN)
                        && (float) ($result->tiebreaker_score ?? PHP_INT_MIN) === (float) ($prevTb ?? PHP_INT_MIN);
                    $assignedPlace = $sameAsPrev ? $prevPlace : $place;
                    $updates[]     = ['id' => $result->id, 'placement' => $assignedPlace, 'updated_at' => $now];
                    $prevPlace     = $assignedPlace;
                } else {
                    $prevPlace = $place;
                }
                $prevScore      = $result->total_score;
                $prevTb         = $result->tiebreaker_score;
                $prevOverridden = $result->placement_overridden;
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

        $event = $division->competitionEvent;
        $cap   = match (true) {
            $results->count() <= 2  => $event->awarded_places_2    ?? 2,
            $results->count() === 3 => $event->awarded_places_3    ?? 3,
            default                => $event->awarded_places_4plus ?? 3,
        };

        foreach ($updates as $update) {
            Result::where('id', $update['id'])
                ->update([
                    'placement'  => $update['placement'] <= $cap ? $update['placement'] : null,
                    'updated_at' => $update['updated_at'],
                ]);
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

    /**
     * Add a penalty to a result. Returns the created MatchPenalty and whether a DQ was triggered.
     *
     * @return array{penalty: MatchPenalty, triggered_dq: bool}
     */
    public function addPenalty(
        Result $result,
        string $type,
        ?string $reason,
        ?RoundRobinMatch $match = null,
        ?Result $opponentResult = null,
    ): array {
        $triggeredDq = false;

        DB::transaction(function () use ($result, $type, $reason, $match, $opponentResult, &$penalty, &$triggeredDq) {
            $penalty = MatchPenalty::create([
                'result_id'           => $result->id,
                'round_robin_match_id' => $match?->id,
                'type'                => $type,
                'reason'              => $reason,
            ]);

            switch ($type) {
                case 'dq':
                    if (! $result->disqualified) {
                        $this->toggleDisqualify($result);
                    }
                    $triggeredDq = true;
                    break;

                case 'forfeit':
                    if (! $result->forfeited) {
                        $result->forceFill(['forfeited' => true])->save();
                    }
                    $triggeredDq = true;
                    break;

                case 'warn':
                    $event = $result->enrolmentEvent->competitionEvent;
                    $autoDqAfter = $event->warnAutoDqAfter();
                    if ($autoDqAfter !== null) {
                        $warnCount = MatchPenalty::where('result_id', $result->id)
                            ->when($match, fn ($q) => $q->where('round_robin_match_id', $match->id))
                            ->where('type', 'warn')
                            ->count();
                        if ($warnCount >= $autoDqAfter && ! $result->disqualified) {
                            $this->toggleDisqualify($result);
                            MatchPenalty::create([
                                'result_id'           => $result->id,
                                'round_robin_match_id' => $match?->id,
                                'type'                => 'dq',
                                'reason'              => "Auto-DQ: {$warnCount} warnings",
                            ]);
                            $triggeredDq = true;
                        }
                    }
                    break;

                case 'deduction':
                    $this->addPoints($result, -1);
                    break;

                case 'opponent_point':
                    if ($opponentResult) {
                        $this->addPoints($opponentResult, 1);
                    }
                    break;
            }
        });

        return ['penalty' => $penalty, 'triggered_dq' => $triggeredDq];
    }

    /**
     * Undo the last penalty for a result (optionally scoped to a bracket match).
     * Returns ['removed' => bool, 'reversed_dq' => bool].
     */
    public function undoLastPenalty(Result $result, ?RoundRobinMatch $match = null): array
    {
        $reversedDq = false;
        $removed    = false;

        DB::transaction(function () use ($result, $match, &$reversedDq, &$removed) {
            $last = MatchPenalty::where('result_id', $result->id)
                ->when($match, fn ($q) => $q->where('round_robin_match_id', $match->id))
                ->where(fn ($q) => $q
                    ->whereNotIn('type', ['dq'])
                    ->orWhere(fn ($q2) => $q2
                        ->where('type', 'dq')
                        ->where(fn ($q3) => $q3->whereNull('reason')->orWhere('reason', 'NOT LIKE', 'Auto-DQ:%'))
                    )
                )
                ->latest()
                ->first();

            if (! $last) return;

            // If undoing a warn that triggered auto-DQ, also remove the auto-DQ penalty
            if ($last->type === 'warn') {
                $event = $result->enrolmentEvent->competitionEvent;
                $autoDqAfter = $event->warnAutoDqAfter();
                if ($autoDqAfter !== null) {
                    $warnCount = MatchPenalty::where('result_id', $result->id)
                        ->when($match, fn ($q) => $q->where('round_robin_match_id', $match->id))
                        ->where('type', 'warn')
                        ->count();
                    if ($warnCount >= $autoDqAfter && $result->disqualified) {
                        // Remove the auto-DQ penalty record
                        MatchPenalty::where('result_id', $result->id)
                            ->when($match, fn ($q) => $q->where('round_robin_match_id', $match->id))
                            ->where('type', 'dq')
                            ->where('reason', 'LIKE', 'Auto-DQ:%')
                            ->latest()
                            ->delete();
                        $this->toggleDisqualify($result);
                        $reversedDq = true;
                    }
                }
            }

            switch ($last->type) {
                case 'dq':
                    if ($result->disqualified) {
                        $result->forceFill(['disqualified' => false, 'placement' => null])->save();
                        if ($result->division_id) {
                            $this->autoRankDivision(Division::find($result->division_id));
                        }
                        $reversedDq = true;
                    }
                    break;

                case 'forfeit':
                    if ($result->forfeited) {
                        $result->forceFill(['forfeited' => false])->save();
                        $reversedDq = true;
                    }
                    break;

                case 'deduction':
                    $this->addPoints($result, 1);
                    break;

                case 'opponent_point':
                    // Find the opponent result via the match
                    if ($last->round_robin_match_id) {
                        $matchRecord = $last->roundRobinMatch;
                        if ($matchRecord) {
                            $opponentEeId = $matchRecord->home_enrolment_event_id === $result->enrolment_event_id
                                ? $matchRecord->away_enrolment_event_id
                                : $matchRecord->home_enrolment_event_id;
                            $opponentResult = $opponentEeId
                                ? Result::where('enrolment_event_id', $opponentEeId)->first()
                                : null;
                            if ($opponentResult) {
                                $this->addPoints($opponentResult, -1);
                            }
                        }
                    }
                    break;
            }

            $last->delete();
            $removed = true;
        });

        return ['removed' => $removed, 'reversed_dq' => $reversedDq];
    }

    public function getActivePenalties(Result $result, ?RoundRobinMatch $match = null)
    {
        return MatchPenalty::where('result_id', $result->id)
            ->when($match, fn ($q) => $q->where('round_robin_match_id', $match->id))
            ->orderBy('created_at')
            ->get();
    }
}
