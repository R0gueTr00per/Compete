<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JudgeScore extends Model
{
    use LogsActivity;

    protected $fillable = ['result_id', 'judge_number', 'score'];

    protected function casts(): array
    {
        return ['score' => 'decimal:3'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }
}
