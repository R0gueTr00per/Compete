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
    ];

    protected function casts(): array
    {
        return [
            'selected_profile_ids'  => 'array',
            'current_profile_index' => 'integer',
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

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
