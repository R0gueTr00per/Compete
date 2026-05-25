<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organisation extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'ai_context',
        'created_by_user_id',
    ];

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
}
