<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreEvent extends Model
{
    protected $fillable = ['result_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }
}
