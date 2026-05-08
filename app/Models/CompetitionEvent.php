<?php

namespace App\Models;

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
        'event_type_id',
        'event_code',
        'running_order',
        'location_label',
        'target_score',
        'scoring_method',
        'judge_count',
        'division_filter',
        'status',
    ];

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
        // Load event type name to get the prefix letter
        $typeName = $event->eventType?->name
            ?? \App\Models\EventType::find($event->event_type_id)?->name
            ?? 'E';

        $prefix = strtoupper(mb_substr($typeName, 0, 1));

        // Count existing events with the same prefix in this competition
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

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
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
        return $this->target_score ?? $this->eventType->default_target_score;
    }

    public function effectiveScoringMethod(): string
    {
        return $this->scoring_method ?? $this->eventType->scoring_method;
    }

    public function effectiveJudgeCount(): int
    {
        return $this->judge_count ?? $this->eventType->judge_count ?? 0;
    }

    public function effectiveDivisionFilter(): string
    {
        return $this->division_filter ?? $this->eventType->division_filter;
    }
}
