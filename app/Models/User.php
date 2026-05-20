<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser, HasName
{
    use HasFactory, Notifiable, HasRoles, CausesActivity;

    protected $fillable = [
        'organisation_id',
        'email',
        'status',
        'timezone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_until'      => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function getFilamentName(): string
    {
        return $this->selfProfile?->full_name ?? $this->email;
    }

    public function getFullNameAttribute(): string
    {
        return $this->getFilamentName();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole('system_admin');
        }

        if ($panel->getId() === 'org-admin') {
            $tenant = app('tenant');
            if (! $tenant) return false;
            $membership = $this->membershipFor($tenant);
            return $this->status === 'active'
                && $membership
                && $membership->status === 'active'
                && in_array($membership->role, ['administrator', 'official']);
        }

        if ($this->status !== 'active') {
            return false;
        }

        // On an org subdomain, require active membership
        $tenant = app('tenant');
        if ($tenant) {
            return $this->canAccessTenant($tenant);
        }

        return true;
    }

    public function canAccessTenant(Organisation $tenant): bool
    {
        return $this->memberships()
            ->where('organisation_id', $tenant->id)
            ->where('status', 'active')
            ->exists();
    }

    public function membershipFor(Organisation $org): ?OrganisationMembership
    {
        return $this->memberships()->where('organisation_id', $org->id)->first();
    }

    public function isOrgAdmin(Organisation $org): bool
    {
        return $this->memberships()
            ->where('organisation_id', $org->id)
            ->where('role', 'administrator')
            ->where('status', 'active')
            ->exists();
    }

    public function isOrgOfficial(Organisation $org): bool
    {
        return $this->memberships()
            ->where('organisation_id', $org->id)
            ->where('role', 'official')
            ->where('status', 'active')
            ->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function lock(): void
    {
        $this->forceFill(['locked_until' => now()->addHour()])->save();
    }

    public function unlock(): void
    {
        $this->forceFill(['locked_until' => null])->save();
    }

    // All profiles owned/managed by this user (own profile + child profiles)
    public function ownedProfiles(): HasMany
    {
        return $this->hasMany(CompetitorProfile::class, 'owner_user_id');
    }

    // The user's own personal profile (profile_type = 'self')
    public function selfProfile(): HasOne
    {
        return $this->hasOne(CompetitorProfile::class, 'owner_user_id')
            ->where('profile_type', 'self');
    }

    // All enrolments across all owned profiles (for admin views)
    public function enrolments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Enrolment::class,
            CompetitorProfile::class,
            'owner_user_id',        // FK on competitor_profiles
            'competitor_profile_id', // FK on enrolments
            'id',                   // local key on users
            'id'                    // local key on competitor_profiles
        );
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class);
    }

    public function organisations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisation_memberships')
            ->withPivot(['role', 'status', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function instructorOf(): HasMany
    {
        return $this->hasMany(\App\Models\Dojo::class, 'instructor_id');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\PortalVerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
