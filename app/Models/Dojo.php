<?php

namespace App\Models;

use App\Models\Enrolment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Dojo extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isUsed(): bool
    {
        return Enrolment::where('dojo_name', $this->name)->exists();
    }
}
