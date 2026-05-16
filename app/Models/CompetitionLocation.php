<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionLocation extends Model
{
    protected $fillable = ['competition_id', 'name', 'sort_order'];

    protected static function booted(): void
    {
        static::deleted(function (self $loc) {
            Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $loc->competition_id))
                ->where('location_label', $loc->name)
                ->update(['location_label' => null, 'status' => 'pending']);
        });

        static::updated(function (self $loc) {
            if ($loc->wasChanged('name')) {
                Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $loc->competition_id))
                    ->where('location_label', $loc->getOriginal('name'))
                    ->update(['location_label' => $loc->name]);
            }
        });
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
