<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionTask extends Model
{
    protected $fillable = [
        'competition_id',
        'title',
        'notes',
        'completed',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'completed'    => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
