<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionBreak extends Model
{
    protected $fillable = [
        'competition_id',
        'competition_day_id',
        'name',
        'start_time',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
        ];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitionDay(): BelongsTo
    {
        return $this->belongsTo(CompetitionDay::class);
    }

    public function endTime(): string
    {
        return \Carbon\Carbon::parse('1970-01-01 ' . $this->start_time)
            ->addMinutes($this->duration_minutes)
            ->format('H:i');
    }
}
