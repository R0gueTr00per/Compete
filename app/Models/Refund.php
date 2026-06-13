<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Refund extends Model
{
    use LogsActivity;

    protected $fillable = [
        'organisation_id',
        'cart_id',
        'enrolment_id',
        'enrolment_event_id',
        'type',
        'amount',
        'reason',
        'payment_method',
        'status',
        'issued_at',
        'issued_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'    => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EnrolmentCart::class, 'cart_id');
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    public function enrolmentEvent(): BelongsTo
    {
        return $this->belongsTo(EnrolmentEvent::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'event_cancelled'   => 'Refund',
            'withdrawal_return' => 'Withdrawal — fee returned',
            'manual'            => 'Refund',
            default             => 'Refund',
        };
    }
}
