<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeightClass extends Model
{
    protected $fillable = ['competition_id', 'label', 'max_kg', 'weight_type', 'sort_order'];

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

    public function getFullLabelAttribute(): string
    {
        if ($this->max_kg === null) {
            return "{$this->label} (Open)";
        }

        $weight = number_format((float) $this->max_kg, 0);

        return $this->weight_type === 'over'
            ? "{$this->label} ({$weight} kg and over)"
            : "{$this->label} (Under {$weight} kg)";
    }

    public function matchesWeight(float $weightKg): bool
    {
        if ($this->max_kg === null) {
            return true;
        }

        return $this->weight_type === 'over'
            ? $weightKg >= (float) $this->max_kg
            : $weightKg < (float) $this->max_kg;
    }
}
