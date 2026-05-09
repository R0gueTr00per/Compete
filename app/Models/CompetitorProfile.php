<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompetitorProfile extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'surname',
        'first_name',
        'date_of_birth',
        'gender',
        'height_cm',
        'phone',
        'profile_complete',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'    => 'date',
            'profile_complete' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Profile {$eventName}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

}
