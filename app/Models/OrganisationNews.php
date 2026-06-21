<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class OrganisationNews extends Model
{
    protected $fillable = [
        'organisation_id',
        'title',
        'content',
        'display_from',
        'display_until',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'display_from'  => 'date',
        'display_until' => 'date',
        'is_visible'    => 'boolean',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('is_visible', true)
            ->where(fn ($q) => $q->whereNull('display_from')->orWhere('display_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('display_until')->orWhere('display_until', '>=', $today));
    }
}
