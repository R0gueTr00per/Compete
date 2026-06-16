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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function instructorsCanAcceptPayments(): bool
    {
        return $this->instructors_can_collect_payments
            && in_array('cash', $this->supported_payment_methods ?? [], true);
    }
}
