<?php

namespace App\Models;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'organisation_id',
        'name',
        'competition_date',
        'start_time',
        'end_time',
        'checkin_time',
        'location_name',
        'location_address',
        'location_url',
        'enrolment_due_date',
        'target_competitors',
        'fee_first_event',
        'fee_additional_event',
        'late_surcharge',
        'fee_official_first_event',
        'fee_official_additional_event',
        'status',
        'copied_from_id',
        'registration_fields',
        'is_template',
        'template_active',
    ];

    protected function casts(): array
    {
        return [
            'competition_date'     => 'date',
            'enrolment_due_date'   => 'date',
            'fee_first_event'               => 'decimal:2',
            'fee_additional_event'          => 'decimal:2',
            'late_surcharge'                => 'decimal:2',
            'fee_official_first_event'      => 'decimal:2',
            'fee_official_additional_event' => 'decimal:2',
            'registration_fields'           => 'array',
            'is_template'                   => 'boolean',
            'template_active'               => 'boolean',
            'completed_at'                  => 'datetime',
        ];
    }

    public function scopeTemplates(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_template', true);
    }

    public function scopeActiveTemplates(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_template', true)->where('template_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function competitionLocations(): HasMany
    {
        return $this->hasMany(CompetitionLocation::class)->orderBy('sort_order');
    }

    protected function locations(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->competitionLocations()->pluck('name')->toArray()
        )->shouldCache();
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

    public function officials(): HasMany
    {
        return $this->hasMany(CompetitionOfficial::class);
    }

    public function isOfficial(User $user): bool
    {
        return $this->officials()->where('user_id', $user->id)->exists();
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'copied_from_id');
    }

    public function insight(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CompetitionInsight::class);
    }

    public function portalMessages(): HasMany
    {
        return $this->hasMany(CompetitionMessage::class)->orderBy('sort_order')->orderBy('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CompetitionTask::class)->orderBy('sort_order');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(CompetitionBreak::class)->orderBy('start_time');
    }

    public function isPublicScheduleAvailable(): bool
    {
        if (in_array($this->status, ['planning', 'advertise', 'open'])) {
            return false;
        }

        if ($this->status === 'complete' && $this->completed_at) {
            return $this->completed_at->isAfter(now()->subDays(7));
        }

        return true;
    }

    public function publicScheduleUrl(): string
    {
        return config('app.scheme') . '://'
            . $this->organisation->slug . '.' . config('app.domain')
            . '/schedule/' . $this->id;
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
