<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OfficialRole extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'organisation_id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function isUsed(): bool
    {
        return CompetitionOfficial::where('official_role_id', $this->id)->exists();
    }
}
