<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundRobinMatch extends Model
{
    protected $fillable = [
        'division_id',
        'home_enrolment_event_id',
        'away_enrolment_event_id',
        'home_result',
        'home_score',
        'away_score',
        'round',
        'bracket',
        'bracket_slot',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function homeEnrolmentEvent(): BelongsTo
    {
        return $this->belongsTo(EnrolmentEvent::class, 'home_enrolment_event_id');
    }

    public function awayEnrolmentEvent(): BelongsTo
    {
        return $this->belongsTo(EnrolmentEvent::class, 'away_enrolment_event_id');
    }

    public function awayResult(): ?string
    {
        return match ($this->home_result) {
            'win'  => 'loss',
            'loss' => 'win',
            'draw' => 'draw',
            default => null,
        };
    }

    public function isPending(): bool
    {
        return $this->home_result === null;
    }

    public function isBye(): bool
    {
        // A true BYE has been auto-won; away=null with home_result=null means still waiting for opponent.
        return $this->away_enrolment_event_id === null && $this->home_result !== null;
    }

    public function winnerId(): ?int
    {
        if ($this->home_result === 'win')  return $this->home_enrolment_event_id;
        if ($this->home_result === 'loss') return $this->away_enrolment_event_id;
        return null;
    }

    public function loserId(): ?int
    {
        if ($this->home_result === 'win')  return $this->away_enrolment_event_id;
        if ($this->home_result === 'loss') return $this->home_enrolment_event_id;
        return null;
    }
}
