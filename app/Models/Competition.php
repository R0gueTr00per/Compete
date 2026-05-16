<?php

namespace App\Models;

use App\Models\Division;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Competition extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'competition_date',
        'start_time',
        'checkin_time',
        'location_name',
        'location_address',
        'enrolment_due_date',
        'fee_first_event',
        'fee_additional_event',
        'late_surcharge',
        'status',
        'copied_from_id',
    ];

    protected function casts(): array
    {
        return [
            'competition_date'     => 'date',
            'enrolment_due_date'   => 'date',
            'fee_first_event'      => 'decimal:2',
            'fee_additional_event' => 'decimal:2',
            'late_surcharge'       => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function competitionLocations(): HasMany
    {
        return $this->hasMany(CompetitionLocation::class)->orderBy('sort_order');
    }

    public function getLocationsAttribute(): array
    {
        return $this->competitionLocations()->pluck('name')->toArray();
    }

    public function competitionEvents(): HasMany
    {
        return $this->hasMany(CompetitionEvent::class);
    }

    public function allDivisions(): HasManyThrough
    {
        return $this->hasManyThrough(Division::class, CompetitionEvent::class);
    }

    public function ageBands(): HasMany
    {
        return $this->hasMany(AgeBand::class)->orderBy('sort_order');
    }

    public function rankBands(): HasMany
    {
        return $this->hasMany(RankBand::class)->orderBy('sort_order');
    }

    public function weightClasses(): HasMany
    {
        return $this->hasMany(WeightClass::class)->orderBy('sort_order');
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'copied_from_id');
    }

    public function isEnrolmentOpen(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }

        return $this->enrolment_due_date === null || $this->enrolment_due_date->isFuture();
    }

    public function isLateEnrolment(): bool
    {
        return $this->enrolment_due_date !== null && $this->enrolment_due_date->isPast();
    }
}
