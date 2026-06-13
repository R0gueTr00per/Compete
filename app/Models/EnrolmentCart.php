<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnrolmentCart extends Model
{
    protected $fillable = [
        'user_id',
        'competition_id',
        'status',
        'selected_profile_ids',
        'current_step',
        'current_profile_index',
        'total_amount',
        'fee_first_rate',
        'fee_additional_rate',
        'fee_official_first_rate',
        'fee_official_additional_rate',
        'late_surcharge_rate',
        'platform_fee_rate',
        'submitted_at',
        'payment_method',
        'payment_status',
        'payment_amount',
        'payment_received_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_profile_ids'         => 'array',
            'current_profile_index'        => 'integer',
            'total_amount'                 => 'decimal:2',
            'fee_first_rate'               => 'decimal:2',
            'fee_additional_rate'          => 'decimal:2',
            'fee_official_first_rate'      => 'decimal:2',
            'fee_official_additional_rate' => 'decimal:2',
            'late_surcharge_rate'          => 'decimal:2',
            'platform_fee_rate'            => 'decimal:2',
            'payment_amount'               => 'decimal:2',
            'submitted_at'                 => 'datetime',
            'payment_received_at'          => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $cart) {
            $cart->enrolments()->where('status', 'draft')->get()->each->delete();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class, 'cart_id');
    }

    public function draftEnrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class, 'cart_id')->where('status', 'draft');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'cart_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'received';
    }

    /** Sum of fees for active (non-withdrawn, non-soft-deleted) enrolments. */
    public function outstandingAmount(float $platformFee = 0): float
    {
        return $this->enrolments
            ->filter(fn ($e) => ! $e->trashed() && $e->status !== 'withdrawn')
            ->sum(fn ($e) => (float) $e->fee_calculated + $platformFee);
    }
}
