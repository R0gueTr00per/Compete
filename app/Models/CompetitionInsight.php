<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionInsight extends Model
{
    protected $fillable = [
        'competition_id',
        'content',
        'data_snapshot',
        'model_used',
        'generated_at',
    ];

    protected $casts = [
        'data_snapshot' => 'array',
        'generated_at'  => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
