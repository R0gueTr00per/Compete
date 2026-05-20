<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationMembership extends Model
{
    protected $fillable = [
        'organisation_id',
        'user_id',
        'role',
        'status',
        'invited_by_user_id',
        'invited_at',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'joined_at'  => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'administrator';
    }
}
