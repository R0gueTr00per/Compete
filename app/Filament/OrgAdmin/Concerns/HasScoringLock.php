<?php

namespace App\Filament\OrgAdmin\Concerns;

use App\Models\Division;

trait HasScoringLock
{
    private const LOCK_MINUTES = 15;

    protected function acquireLock(int $divisionId): void
    {
        Division::where('id', $divisionId)->update([
            'scoring_locked_by' => auth()->id(),
            'scoring_locked_at' => now(),
        ]);
    }

    protected function releaseLock(?int $divisionId): void
    {
        if (! $divisionId) return;
        Division::where('id', $divisionId)
            ->where('scoring_locked_by', auth()->id())
            ->update(['scoring_locked_by' => null, 'scoring_locked_at' => null]);
    }

    protected function lockedByOtherName(Division $division): ?string
    {
        if (! $division->scoring_locked_by) return null;
        if ($division->scoring_locked_by === auth()->id()) return null;
        if (! $division->scoring_locked_at || $division->scoring_locked_at->lt(now()->subMinutes(self::LOCK_MINUTES))) return null;
        return $division->scoringLockedBy?->name ?? 'Another user';
    }
}
