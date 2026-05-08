<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Division extends Model
{
    use LogsActivity;

    protected $fillable = [
        'competition_event_id',
        'code',
        'age_band_id',
        'rank_band_id',
        'weight_class_id',
        'sex',
        'label',
        'target_score',
        'running_order',
        'location_label',
        'status',
        'combined_into_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    protected static function booted(): void
    {
        static::saving(function (self $division) {
            // Auto-generate label from bands/sex whenever they change
            if ($division->isDirty(['age_band_id', 'rank_band_id', 'weight_class_id', 'sex'])) {
                $parts = array_filter([
                    $division->age_band_id    ? AgeBand::find($division->age_band_id)?->label    : null,
                    $division->rank_band_id   ? RankBand::find($division->rank_band_id)?->label   : null,
                    $division->weight_class_id ? WeightClass::find($division->weight_class_id)?->label : null,
                    match ($division->sex) { 'M' => 'Male', 'F' => 'Female', default => null },
                ]);
                if (! empty($parts)) {
                    $division->label = implode(' / ', $parts);
                }
            }

            // Status transitions driven by location assignment
            if ($division->isDirty('location_label')) {
                if ($division->location_label) {
                    if (in_array($division->status, ['pending', 'cancelled'])) {
                        $division->status = 'assigned';
                    }
                } else {
                    if ($division->status === 'assigned') {
                        $division->status = 'cancelled';
                    }
                }
            }
        });
    }

    public function getFullLabelAttribute(): string
    {
        return $this->code ? "{$this->code} — {$this->label}" : $this->label;
    }

    public function competitionEvent(): BelongsTo
    {
        return $this->belongsTo(CompetitionEvent::class);
    }

    public function ageBand(): BelongsTo
    {
        return $this->belongsTo(AgeBand::class);
    }

    public function rankBand(): BelongsTo
    {
        return $this->belongsTo(RankBand::class);
    }

    public function weightClass(): BelongsTo
    {
        return $this->belongsTo(WeightClass::class);
    }

    public function combinedInto(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'combined_into_id');
    }

    public function enrolmentEvents(): HasMany
    {
        return $this->hasMany(EnrolmentEvent::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function activeEnrolmentEvents(): HasMany
    {
        return $this->hasMany(EnrolmentEvent::class)->where('removed', false);
    }
}
