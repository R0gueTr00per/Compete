<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPenalty extends Model
{
    protected $fillable = [
        'result_id',
        'round_robin_match_id',
        'type',
        'reason',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function roundRobinMatch(): BelongsTo
    {
        return $this->belongsTo(RoundRobinMatch::class);
    }
}
