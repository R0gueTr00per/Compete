<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompetitionOfficial extends Model
{
    use LogsActivity;

    protected $fillable = [
        'competition_id',
        'user_id',
        'official_role_id',
        'competition_location_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officialRole(): BelongsTo
    {
        return $this->belongsTo(OfficialRole::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(CompetitionLocation::class, 'competition_location_id');
    }
}
