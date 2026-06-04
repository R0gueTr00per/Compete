<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompetitionEvent extends Model
{
    use LogsActivity;

    protected $fillable = [
        'competition_id',
        'name',
        'event_code',
        'running_order',
        'scoring_method',
        'tournament_format',
        'manual_pairing',
        'bracket_sort',
        'bracket_first_round_order',
        'bracket_prefer_different_dojo',
        'bracket_avoid_repeat_matchups',
        'judge_count',
        'target_score',
        'default_score',
        'division_filter',
        'requires_partner',
        'rollcall_required',
        'status',
        'awarded_places_2',
        'awarded_places_3',
        'awarded_places_4plus',
        'default_max_competitors',
        'round_duration_seconds',
        'tiebreak_duration_seconds',
        'tiebreak_mode',
        'overtime_rounds',
        'increment_buttons',
        'penalty_config',
    ];

    protected function casts(): array
    {
        return [
            'requires_partner'              => 'boolean',
            'rollcall_required'             => 'boolean',
            'manual_pairing'                => 'boolean',
            'bracket_prefer_different_dojo' => 'boolean',
            'bracket_avoid_repeat_matchups' => 'boolean',
            'awarded_places_2'              => 'integer',
            'awarded_places_3'              => 'integer',
            'awarded_places_4plus'          => 'integer',
            'increment_buttons'             => 'array',
            'penalty_config'                => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if (! $event->event_code) {
                $event->event_code = static::generateCode($event);
            }
        });
    }

    private static function generateCode(self $event): string
    {
        $words = preg_split('/\s+/', trim($event->name ?? 'E'));
        $prefix = count($words) === 1
            ? strtoupper(mb_substr($event->name, 0, 2))
            : strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));

        if (! static::where('competition_id', $event->competition_id)->where('event_code', $prefix)->exists()) {
            return $prefix;
        }

        $suffix = 2;
        while (static::where('competition_id', $event->competition_id)->where('event_code', $prefix . $suffix)->exists()) {
            $suffix++;
        }
        return $prefix . $suffix;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function enrolmentEvents(): HasMany
    {
        return $this->hasMany(EnrolmentEvent::class);
    }

    public function supportsPenalties(): bool
    {
        return in_array($this->effectiveScoringMethod(), ['win_loss', 'first_to_n', 'timed_points']);
    }

    public function isPenaltyTypeEnabled(string $type): bool
    {
        return (bool) ($this->penalty_config[$type]['enabled'] ?? false);
    }

    public function enabledPenaltyTypes(): array
    {
        $config = $this->penalty_config ?? [];
        return array_keys(array_filter($config, fn ($c) => $c['enabled'] ?? false));
    }

    public function penaltyReasonsFor(string $type): array
    {
        $reasons = $this->penalty_config[$type]['reasons'] ?? [];
        return array_values(array_filter((array) $reasons));
    }

    public function warnAutoDqAfter(): ?int
    {
        $val = $this->penalty_config['warn']['auto_dq_after'] ?? null;
        return $val !== null ? (int) $val : null;
    }

    public function effectiveTargetScore(): ?int
    {
        return $this->target_score;
    }

    public function getIncrementButtons(): array
    {
        $buttons = $this->increment_buttons;
        if (empty($buttons)) {
            return [1];
        }
        return array_map(fn ($v) => (float) $v == (int) (float) $v ? (int) (float) $v : (float) $v, $buttons);
    }

    public function effectiveScoringMethod(): string
    {
        return $this->scoring_method ?? 'judges_total';
    }

    public function effectiveTournamentFormat(): string
    {
        return $this->tournament_format ?? 'once_off';
    }

    public function effectiveJudgeCount(): int
    {
        return $this->judge_count ?? 3;
    }

    public function effectiveDivisionFilter(): string
    {
        return $this->division_filter ?? 'age_rank_sex';
    }

    public function isTournament(): bool
    {
        return in_array($this->effectiveTournamentFormat(), ['round_robin', 'single_elimination', 'double_elimination', 'repechage', 'se_3rd_place']);
    }

    public function hasTimer(): bool
    {
        return $this->round_duration_seconds !== null;
    }

    public function hasTiebreakerTimer(): bool
    {
        return $this->tiebreak_duration_seconds !== null;
    }

    public function getTiebreakerMode(): string
    {
        return $this->tiebreak_mode ?? 'sudden_death';
    }

    public function getOvertimeRounds(): int
    {
        return $this->overtime_rounds ?? 1;
    }
}
