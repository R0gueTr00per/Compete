<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EnrolmentEvent extends Model
{
    use LogsActivity;

    protected $fillable = [
        'enrolment_id',
        'competition_event_id',
        'division_id',
        'partner_enrolment_event_id',
        'yakusuko_complete',
        'weight_confirmed_kg',
        'removed',
        'removed_at',
        'removed_by',
        'removal_reason',
    ];

    protected function casts(): array
    {
        return [
            'yakusuko_complete'   => 'boolean',
            'removed'             => 'boolean',
            'removed_at'          => 'datetime',
            'weight_confirmed_kg' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    public function competitionEvent(): BelongsTo
    {
        return $this->belongsTo(CompetitionEvent::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(EnrolmentEvent::class, 'partner_enrolment_event_id');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    public function competitor(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(User::class, Enrolment::class, 'id', 'id', 'enrolment_id', 'competitor_id');
    }
}
