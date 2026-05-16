<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Result extends Model
{
    use LogsActivity;

    protected $fillable = [
        'division_id',
        'enrolment_event_id',
        'total_score',
        'tiebreaker_score',
        'win_loss',
    ];

    protected function casts(): array
    {
        return [
            'placement_overridden' => 'boolean',
            'disqualified'         => 'boolean',
            'total_score'          => 'decimal:3',
            'tiebreaker_score'     => 'decimal:3',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function enrolmentEvent(): BelongsTo
    {
        return $this->belongsTo(EnrolmentEvent::class);
    }

    public function judgeScores(): HasMany
    {
        return $this->hasMany(JudgeScore::class)->orderBy('judge_number');
    }
}
