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

    public function getFullLabelAttribute(): string
    {
        $thisMax = $this->max_kg !== null ? (float) $this->max_kg : null;

        if ($thisMax !== null) {
            $prevMax = static::where('competition_id', $this->competition_id)
                ->whereNotNull('max_kg')
                ->where('max_kg', '<', $thisMax)
                ->orderByDesc('max_kg')
                ->value('max_kg');
        } else {
            $prevMax = static::where('competition_id', $this->competition_id)
                ->whereNotNull('max_kg')
                ->orderByDesc('max_kg')
                ->value('max_kg');
        }

        $prevMax = $prevMax !== null ? (float) $prevMax : null;

        if ($prevMax === null && $thisMax !== null) {
            $range = number_format($thisMax, 0) . ' kg & under';
        } elseif ($thisMax === null) {
            $range = 'over ' . number_format((float) $prevMax, 0) . ' kg';
        } else {
            $range = 'over ' . number_format($prevMax, 0) . ' to ' . number_format($thisMax, 0) . ' kg';
        }

        return "{$this->label} ({$range})";
    }

    public function matchesWeight(float $weightKg): bool
    {
        return $this->max_kg === null || $weightKg <= $this->max_kg;
    }
}
