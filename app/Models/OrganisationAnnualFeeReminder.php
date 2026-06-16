<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationAnnualFeeReminder extends Model
{
    protected $fillable = [
        'organisation_id',
        'due_date',
        'amount',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date'     => 'date',
            'amount'       => 'decimal:2',
            'dismissed_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }

    public function dismiss(): void
    {
        $this->update(['dismissed_at' => now()]);
    }
}
