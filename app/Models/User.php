<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser, HasName
{
    use HasFactory, Notifiable, HasRoles, CausesActivity;

    protected $fillable = [
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
        $profile = $this->competitorProfile;
        if ($profile) {
            $name = trim($profile->first_name . ' ' . $profile->surname);
            if ($name !== '') {
                return $name;
            }
        }

        return $this->email;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole(['competition_administrator', 'system_admin', 'competition_official']);
        }

        // Portal: active competitors, plus admin roles (so they can view the competitor experience)
        return $this->status === 'active';
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

    public function competitorProfile(): HasOne
    {
        return $this->hasOne(CompetitorProfile::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class, 'competitor_id');
    }

    public function instructorOf(): HasMany
    {
        return $this->hasMany(\App\Models\Dojo::class, 'instructor_id');
    }
}
