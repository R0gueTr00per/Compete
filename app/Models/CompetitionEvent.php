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
        'location_label',
        'scoring_method',
        'tournament_format',
        'judge_count',
        'target_score',
        'default_score',
        'division_filter',
        'requires_partner',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'requires_partner' => 'boolean',
        ];
    }

    protected function requiresWeightCheck(): Attribute
    {
        return Attribute::make(
            get: fn () => isset($this->attributes['has_weight_divisions'])
                ? (bool) $this->attributes['has_weight_divisions']
                : $this->divisions()->whereNotNull('weight_class_id')->exists(),
        )->shouldCache();
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
        $prefix = strtoupper(mb_substr($event->name ?? 'E', 0, 1));

        $count = static::where('competition_id', $event->competition_id)
            ->where('event_code', 'like', $prefix . '%')
            ->count();

        return $prefix . str_pad($count + 1, 2, '0', STR_PAD_LEFT);
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

    public function effectiveTargetScore(): ?int
    {
        return $this->target_score;
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
}
