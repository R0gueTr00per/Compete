<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OfficialRole extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'organisation_id',
        'can_access_enrolments',
        'can_access_checkin',
        'can_access_create_enrolment',
        'can_access_scoring',
    ];

    protected function casts(): array
    {
        return [
            'can_access_enrolments'      => 'boolean',
            'can_access_checkin'         => 'boolean',
            'can_access_create_enrolment' => 'boolean',
            'can_access_scoring'         => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function isUsed(): bool
    {
        return CompetitionOfficial::where('official_role_id', $this->id)->exists();
    }
}
