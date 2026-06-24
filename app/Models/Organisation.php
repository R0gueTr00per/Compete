<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Organisation extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'contact_phone',
        'contact_email',
        'website',
        'logo',
        'ai_context',
        'ai_tone_presets',
        'auto_email_insights',
        'insights_auto_refresh',
        'competitor_summaries_enabled',
        'dashboard_closed_days',
        'timezone',
        'date_format',
        'currency',
        'platform_fee',
        'annual_fee',
        'annual_fee_anniversary_date',
        'gst_registered',
        'gst_rate',
        'competitor_logins_locked',
        'cancellation_days_before',
        'group_name',
        'supported_payment_methods',
        'instructors_can_collect_payments',
        'created_by_user_id',
    ];

    protected $casts = [
        'ai_tone_presets'          => 'array',
        'auto_email_insights'      => 'boolean',
        'insights_auto_refresh'           => 'boolean',
        'competitor_summaries_enabled'    => 'boolean',
        'dashboard_closed_days'           => 'integer',
        'platform_fee'             => 'decimal:2',
        'annual_fee'               => 'decimal:2',
        'annual_fee_anniversary_date' => 'date',
        'gst_registered'            => 'boolean',
        'gst_rate'                  => 'decimal:2',
        'competitor_logins_locked'  => 'boolean',
        'cancellation_days_before'     => 'integer',
        'supported_payment_methods'    => 'array',
        'instructors_can_collect_payments' => 'boolean',
    ];

    protected static function booted(): void
    {
        $clearCache = function (self $org) {
            Cache::forget("org:slug:{$org->slug}");
            if ($org->getOriginal('slug')) {
                Cache::forget('org:slug:' . $org->getOriginal('slug'));
            }
        };
        static::saved($clearCache);
        static::deleted($clearCache);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, OrganisationMembership::class, 'organisation_id', 'id', 'id', 'user_id');
    }

    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    public function nextCompetition(): HasOne
    {
        return $this->hasOne(Competition::class)
            ->where('competition_date', '>=', now()->toDateString())
            ->orderBy('competition_date');
    }

    public function competitorProfiles(): HasMany
    {
        return $this->hasMany(CompetitorProfile::class);
    }

    public function dojos(): HasMany
    {
        return $this->hasMany(Dojo::class);
    }

    public function annualFeeReminders(): HasMany
    {
        return $this->hasMany(OrganisationAnnualFeeReminder::class);
    }

    public function news(): HasMany
    {
        return $this->hasMany(OrganisationNews::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * The next occurrence of the annual fee anniversary on or after today.
     * Clamps the day for short months (e.g. a 31st anniversary in February).
     */
    public function nextAnnualFeeDueDate(): ?\Illuminate\Support\Carbon
    {
        if (! $this->annual_fee_anniversary_date) {
            return null;
        }

        $today = now()->startOfDay();
        $month = $this->annual_fee_anniversary_date->month;
        $day   = $this->annual_fee_anniversary_date->day;

        $dueThisYear = \Illuminate\Support\Carbon::create($today->year, $month, 1)->startOfDay();
        $dueThisYear->day(min($day, $dueThisYear->daysInMonth));

        if ($dueThisYear->isBefore($today)) {
            $dueNextYear = \Illuminate\Support\Carbon::create($today->year + 1, $month, 1)->startOfDay();
            $dueNextYear->day(min($day, $dueNextYear->daysInMonth));
            return $dueNextYear;
        }

        return $dueThisYear;
    }

    public function instructorsCanAcceptPayments(): bool
    {
        return $this->instructors_can_collect_payments
            && in_array('cash', $this->supported_payment_methods ?? [], true);
    }
}
