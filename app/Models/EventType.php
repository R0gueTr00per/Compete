<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventType extends Model
{
    protected $fillable = [
        'name',
        'scoring_method',
        'division_filter',
        'requires_partner',
        'requires_weight_check',
        'default_target_score',
        'judge_count',
    ];

    protected function casts(): array
    {
        return [
            'requires_partner' => 'boolean',
            'requires_weight_check' => 'boolean',
        ];
    }

    public function competitionEvents(): HasMany
    {
        return $this->hasMany(CompetitionEvent::class);
    }

    public function usesJudges(): bool
    {
        return in_array($this->scoring_method, ['judges_total', 'judges_average']);
    }

    public function usesWinLoss(): bool
    {
        return $this->scoring_method === 'win_loss';
    }

    public function usesPoints(): bool
    {
        return $this->scoring_method === 'first_to_n';
    }
}
