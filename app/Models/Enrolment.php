<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Enrolment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'competition_id',
        'competitor_id',
        'enrolled_at',
        'is_late',
        'status',
        'checked_in',
        'checked_in_at',
        'rank_type',
        'rank_kyu',
        'rank_dan',
        'experience_years',
        'experience_months',
        'weight_kg',
        'dojo_type',
        'dojo_name',
        'guest_style',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at'    => 'datetime',
            'is_late'        => 'boolean',
            'fee_calculated' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'checked_in'     => 'boolean',
            'checked_in_at'  => 'datetime',
            'weight_kg'      => 'decimal:2',
        ];
    }

    public function isPaymentOutstanding(): bool
    {
        return $this->payment_status !== 'received';
    }

    public function getDisplayRankAttribute(): string
    {
        return match ($this->rank_type) {
            'kyu'        => $this->rank_kyu . ' Kyu',
            'dan'        => $this->rank_dan . ' Dan',
            'experience' => trim(
                ($this->experience_years ? $this->experience_years . 'y ' : '') .
                ($this->experience_months ? $this->experience_months . 'm' : '')
            ) . ' experience',
            default => '—',
        };
    }

    public function normalizeRank(): ?int
    {
        return match ($this->rank_type) {
            'kyu'        => $this->rank_kyu ? -$this->rank_kyu : null,
            'dan'        => $this->rank_dan ?? null,
            'experience' => 0,
            default      => null,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'competitor_id');
    }

    public function enrolmentEvents(): HasMany
    {
        return $this->hasMany(EnrolmentEvent::class);
    }

    public function activeEvents(): HasMany
    {
        return $this->hasMany(EnrolmentEvent::class)->where('removed', false);
    }
}
