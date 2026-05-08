<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgeBand extends Model
{
    protected $fillable = ['competition_id', 'label', 'min_age', 'max_age', 'sort_order'];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function matchesAge(int $age): bool
    {
        if ($this->min_age !== null && $age < $this->min_age) {
            return false;
        }

        if ($this->max_age !== null && $age > $this->max_age) {
            return false;
        }

        return true;
    }
}
