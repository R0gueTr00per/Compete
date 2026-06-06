<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JudgeScoreDetail extends Model
{
    protected $fillable = ['judge_score_id', 'score_category_id', 'score'];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:3',
        ];
    }

    public function judgeScore(): BelongsTo
    {
        return $this->belongsTo(JudgeScore::class);
    }

    public function scoreCategory(): BelongsTo
    {
        return $this->belongsTo(ScoreCategory::class);
    }
}
