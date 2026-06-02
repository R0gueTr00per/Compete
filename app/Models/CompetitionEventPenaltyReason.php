<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionEventPenaltyReason extends Model
{
    protected $fillable = [
        'competition_event_id',
        'penalty_type',
        'reason',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function competitionEvent(): BelongsTo
    {
        return $this->belongsTo(CompetitionEvent::class);
    }
}
