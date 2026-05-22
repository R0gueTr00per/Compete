<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rank extends Model
{
    protected $fillable = ['name', 'sort_order', 'organisation_id'];

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }
}
