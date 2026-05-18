<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompetitorProfile extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'owner_user_id',
        'profile_type',
        'surname',
        'first_name',
        'date_of_birth',
        'gender',
        'phone',
        'profile_photo',
        'profile_complete',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'    => 'date',
            'profile_complete' => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Profile {$eventName}");
    }

    // The user who manages/owns this profile (parent for child profiles, self for self-profiles)
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    // The profile's own login account (only set when the profile has graduated to its own account,
    // or for self-profiles where user_id == owner_user_id)
    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class, 'competitor_profile_id');
    }

    public function isChild(): bool
    {
        return $this->profile_type === 'child';
    }

    public function hasDedicatedAccount(): bool
    {
        return $this->user_id !== null;
    }

    // Returns the User to notify for this profile (own account if graduated, otherwise the owner)
    public function notifiableUser(): ?User
    {
        return $this->account ?? $this->owner;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->surname);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
