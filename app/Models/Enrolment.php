<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Enrolment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'cart_id',
        'competition_id',
        'competitor_profile_id',
        'enrolled_at',
        'is_late',
        'is_official_discount',
        'status',
        'checkin_code',
        'checked_in',
        'checked_in_at',
        'rank_id',
        'weight_kg',
        'dojo_type',
        'dojo_name',
        'guest_style',
        'custom_field_responses',
        'withdrawn_at',
        'withdrawal_reason',
        'refund_requested',
        'payment_received_at',
        'ai_summary',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at'          => 'datetime',
            'is_late'              => 'boolean',
            'is_official_discount' => 'boolean',
            'fee_calculated'       => 'decimal:2',
            'payment_amount'       => 'decimal:2',
            'checked_in'           => 'boolean',
            'checked_in_at'        => 'datetime',
            'weight_kg'            => 'decimal:2',
            'custom_field_responses' => 'array',
            'withdrawn_at'         => 'datetime',
            'refund_requested'     => 'boolean',
            'payment_received_at'  => 'datetime',
        ];
    }

    public function isPaymentOutstanding(): bool
    {
        return $this->payment_status !== 'received';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function canWithdraw(): bool
    {
        if (in_array($this->status, ['withdrawn', 'checked_in', 'draft'])) {
            return false;
        }

        $competition = $this->competition;
        if (now()->isAfter($competition->competition_date->endOfDay())) {
            return false;
        }

        $cutoffDays = (int) ($competition->organisation->cancellation_days_before ?? 0);
        if ($cutoffDays > 0 && now()->gte($competition->competition_date->subDays($cutoffDays))) {
            return false;
        }

        return true;
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EnrolmentCart::class, 'cart_id');
    }

    public function getDisplayRankAttribute(): string
    {
        return $this->rank?->name ?? '—';
    }

    protected static function booted(): void
    {
        static::creating(function (self $enrolment) {
            if (empty($enrolment->checkin_code)) {
                $enrolment->checkin_code = static::generateUniqueCheckinCode();
            }
        });
    }

    private static function generateUniqueCheckinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = strlen($alphabet);
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, $len - 1)];
            }
        } while (static::where('checkin_code', $code)->exists());

        return $code;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(CompetitorProfile::class, 'competitor_profile_id');
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
