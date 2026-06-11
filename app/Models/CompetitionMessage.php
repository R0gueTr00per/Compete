<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionMessage extends Model
{
    protected $fillable = ['competition_id', 'message', 'sort_order'];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
