<?php

namespace App\Livewire\OrgAdmin\Concerns;

use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\MatchPenalty;
use App\Models\Result;
use Livewire\Attributes\Computed;

trait HasDivisionScoring
{
    #[Computed]
    public function selectedDivision(): ?Division
    {
        if (! $this->division_id) return null;
        return Division::with(['competitionEvent', 'completedBy.selfProfile'])->find($this->division_id);
    }

    public function isTournament(): bool
    {
        return in_array($this->getTournamentFormat(), ['round_robin', 'single_elimination', 'double_elimination', 'repechage', 'se_3rd_place']);
    }

    public function isRoundRobin(): bool
    {
        return $this->getTournamentFormat() === 'round_robin';
    }

    public function getTournamentFormat(): ?string
    {
        $div = $this->selectedDivision;
        return $div?->tournament_format ?? $div?->competitionEvent->effectiveTournamentFormat();
    }

    public function getScoringMethod(): ?string
    {
        $div = $this->selectedDivision;
        if (! $div) return null;
        return $div->scoring_method ?? $div->competitionEvent->effectiveScoringMethod();
    }

    public function getJudgeCount(): int
    {
        $div = $this->selectedDivision;
        if (! $div) return 3;
        return $div->competitionEvent->effectiveJudgeCount();
    }

    public function getScoreCategories(): \Illuminate\Support\Collection
    {
        $div = $this->selectedDivision;
        if (! $div) return collect();

        $mode = $div->competitionEvent->score_category_mode ?? 'single';
        if ($mode === 'single') return collect();

        if (! empty($div->category_config)) {
            return collect($div->category_config)->map(fn ($c) => (object) $c);
        }

        return $div->competitionEvent->scoreCategories()->get();
    }

    public function getTargetScore(): ?int
    {
        $div = $this->selectedDivision;
        if (! $div) return null;
        return $div->competitionEvent->effectiveTargetScore();
    }

    public function getRoundDuration(): ?int
    {
        $div = $this->selectedDivision;
        if (! $div) return null;
        return $div->competitionEvent->round_duration_seconds;
    }

    public function getTiebreakerDuration(): ?int
    {
        $div = $this->selectedDivision;
        if (! $div) return null;
        return $div->competitionEvent->tiebreak_duration_seconds;
    }

    public function getTiebreakerMode(): string
    {
        $div = $this->selectedDivision;
        if (! $div) return 'sudden_death';
        return $div->competitionEvent->getTiebreakerMode();
    }

    public function getOvertimeRounds(): int
    {
        $div = $this->selectedDivision;
        if (! $div) return 1;
        return $div->competitionEvent->getOvertimeRounds();
    }

    public function getIncrementButtons(): array
    {
        $div = $this->selectedDivision;
        if (! $div) return [1];
        return $div->competitionEvent->getIncrementButtons();
    }

    public function getAwardedPlacesLabel(): string
    {
        if (! $this->division_id) return '';
        $division = $this->selectedDivision;
        if (! $division) return '';

        $dayId = $division?->competition_day_id;
        $count = EnrolmentEvent::where('division_id', $this->division_id)
            ->where('removed', false)
            ->when(
                $dayId,
                fn ($q, $id) => $q->whereHas('enrolment.checkIns', fn ($q2) => $q2->where('competition_day_id', $id)),
                fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('status', 'checked_in'))
            )
            ->count();

        $event = $division->competitionEvent;
        $cap   = match (true) {
            $count <= 2  => $event->awarded_places_2    ?? 2,
            $count === 3 => $event->awarded_places_3    ?? 3,
            default      => $event->awarded_places_4plus ?? 3,
        };

        return match ($cap) {
            1       => '1st only',
            2       => '1st & 2nd',
            default => 'Podium',
        };
    }

    public function getScoringSettingPills(): array
    {
        $div = $this->selectedDivision;
        if (! $div) return [];

        $pills = [];

        $pills[] = match ($this->getTournamentFormat()) {
            'once_off'           => 'Single Perf',
            'single_elimination' => 'Single Elim',
            'double_elimination' => 'Double Elim',
            'round_robin'        => 'Round Robin',
            'se_3rd_place'       => 'SE 3rd Place',
            default              => $this->getTournamentFormat(),
        };

        $pills[] = match ($this->getScoringMethod()) {
            'judges_average' => 'Judges Avg',
            'judges_total'   => 'Judges Total',
            'win_loss'       => 'Win/Loss',
            'first_to_n'     => 'First to N',
            'timed_points'   => 'Timed Pts',
            default          => $this->getScoringMethod(),
        };

        if ($div->competitionEvent->high_low_drop) {
            $pills[] = 'Hi-Low Drop';
        }

        return $pills;
    }

    public function getEnabledPenaltyTypes(): array
    {
        $div = $this->selectedDivision;
        if (! $div) return [];
        $order   = ['warn', 'deduction', 'opponent_point', 'dq', 'forfeit'];
        $enabled = $div->competitionEvent->enabledPenaltyTypes();
        usort($enabled, fn ($a, $b) => array_search($a, $order) <=> array_search($b, $order));
        return $enabled;
    }

    public function hasPenalties(): bool
    {
        return ! empty($this->getEnabledPenaltyTypes());
    }

    public function getPenaltyLabel(string $type): string
    {
        return match ($type) {
            'warn'           => 'Warn',
            'dq'             => 'DQ',
            'forfeit'        => 'Forfeit',
            'deduction'      => '-1',
            'opponent_point' => '+1 Opp',
            default          => $type,
        };
    }

    public function getDqLabel(int $resultId): string
    {
        $result = $this->findResult($resultId);
        return $result?->forfeited ? 'Forfeit' : 'DQ';
    }

    #[Computed]
    public function allPenalties(): \Illuminate\Support\Collection
    {
        if (! $this->division_id) return collect();
        $resultIds = Result::where('division_id', $this->division_id)->pluck('id');
        if ($resultIds->isEmpty()) return collect();
        return MatchPenalty::whereIn('result_id', $resultIds)->orderBy('created_at')->get();
    }

    public function getWarnCount(int $resultId, ?int $matchId = null): int
    {
        return $this->allPenalties
            ->where('result_id', $resultId)
            ->where('type', 'warn')
            ->when($matchId, fn ($c) => $c->where('round_robin_match_id', $matchId))
            ->count();
    }

    public function getPenaltyLog(int $resultId, ?int $matchId = null): array
    {
        $penalties = $this->allPenalties
            ->where('result_id', $resultId)
            ->when($matchId, fn ($c) => $c->where('round_robin_match_id', $matchId));

        $warnCount = 0;
        $log       = [];
        foreach ($penalties as $penalty) {
            if ($penalty->type === 'warn') {
                $warnCount++;
                $ordinal = match ($warnCount) {
                    1 => '1st', 2 => '2nd', 3 => '3rd',
                    default => "{$warnCount}th",
                };
                $log[] = ['id' => $penalty->id, 'label' => "{$ordinal} warning" . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'dq') {
                $log[] = ['id' => $penalty->id, 'label' => 'DQ' . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'forfeit') {
                $log[] = ['id' => $penalty->id, 'label' => 'Forfeit' . ($penalty->reason ? " — {$penalty->reason}" : '')];
            } elseif ($penalty->type === 'deduction') {
                $log[] = ['id' => $penalty->id, 'label' => '-1 deduction'];
            } elseif ($penalty->type === 'opponent_point') {
                $log[] = ['id' => $penalty->id, 'label' => '+1 to opponent'];
            }
        }
        return $log;
    }

    public function hasUndoablePenalty(int $resultId, ?int $matchId = null): bool
    {
        return $this->allPenalties
            ->where('result_id', $resultId)
            ->when($matchId, fn ($c) => $c->where('round_robin_match_id', $matchId))
            ->first(fn ($p) =>
                $p->type !== 'dq' ||
                ($p->type === 'dq' && (is_null($p->reason) || ! str_starts_with($p->reason ?? '', 'Auto-DQ:')))
            ) !== null;
    }

    protected function findResult(int $resultId): ?Result
    {
        $result = Result::find($resultId);
        if (! $result || ! $this->division_id) return null;
        if (! EnrolmentEvent::where('id', $result->enrolment_event_id)
            ->where('division_id', $this->division_id)
            ->exists()) {
            return null;
        }
        return $result;
    }

    protected function resolveEeName(?EnrolmentEvent $ee): string
    {
        if (! $ee) return '—';
        return $ee->enrolment->competitor?->full_name ?? '—';
    }

    protected function buildRollcallInfo(EnrolmentEvent $ee, string $filter): string
    {
        $parts = [];

        if (str_contains($filter, 'age')) {
            $age = $ee->enrolment->competitor?->age;
            if ($age !== null) $parts[] = $age . 'yo';
        }
        if (str_contains($filter, 'weight')) {
            $kg = $ee->enrolment->weight_kg;
            if ($kg) $parts[] = $kg . 'kg';
        }
        if (str_contains($filter, 'rank')) {
            $rank = $ee->enrolment->rank?->name;
            if ($rank) $parts[] = $rank;
        }
        if (str_contains($filter, 'sex')) {
            $gender = $ee->enrolment->competitor?->gender;
            if ($gender) $parts[] = match ($gender) {
                'M' => 'Male',
                'F' => 'Female',
                default => $gender,
            };
        }

        return $parts ? implode(', ', $parts) : '';
    }

    protected function buildPairingInfo(EnrolmentEvent $ee, string $filter): string
    {
        $parts = [];

        if (str_contains($filter, 'age')) {
            $age = $ee->enrolment->competitor?->age;
            if ($age !== null) $parts[] = $age . 'yo';
        }
        $rank = $ee->enrolment->rank?->name;
        if ($rank) $parts[] = $rank;
        $kg = $ee->weight_confirmed_kg ?? $ee->enrolment->weight_kg;
        if ($kg) $parts[] = rtrim(rtrim(number_format((float) $kg, 1), '0'), '.') . 'kg';
        if (str_contains($filter, 'sex')) {
            $gender = $ee->enrolment->competitor?->gender;
            if ($gender) $parts[] = match ($gender) {
                'M' => 'Male',
                'F' => 'Female',
                default => $gender,
            };
        }

        return implode(', ', $parts);
    }

    protected function divisionDayId(): ?int
    {
        return Division::where('id', $this->division_id)->value('competition_day_id');
    }
}
