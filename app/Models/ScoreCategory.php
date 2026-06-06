<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoreCategory extends Model
{
    protected $fillable = ['competition_event_id', 'name', 'weight', 'sort_order'];

    protected function casts(): array
    {
        return [
            'weight'     => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function competitionEvent(): BelongsTo
    {
        return $this->belongsTo(CompetitionEvent::class);
    }

    public function judgeScoreDetails(): HasMany
    {
        return $this->hasMany(JudgeScoreDetail::class);
    }
}
