<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeightClass extends Model
{
    protected $fillable = ['competition_id', 'label', 'max_kg', 'sort_order'];

    protected function casts(): array
    {
        return ['max_kg' => 'decimal:2'];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function matchesWeight(float $weightKg): bool
    {
        return $this->max_kg === null || $weightKg <= $this->max_kg;
    }
}
