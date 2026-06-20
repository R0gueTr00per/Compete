<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrolmentCheckIn extends Model
{
    protected $fillable = [
        'enrolment_id',
        'competition_day_id',
        'checked_in_at',
        'weight_kg',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'weight_kg'     => 'decimal:2',
        ];
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    public function competitionDay(): BelongsTo
    {
        return $this->belongsTo(CompetitionDay::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
