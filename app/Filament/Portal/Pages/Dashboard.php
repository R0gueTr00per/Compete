<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.portal.pages.dashboard';

    public function getProfiles()
    {
        return auth()->user()->ownedProfiles()
            ->orderByRaw("CASE WHEN profile_type = 'self' THEN 0 ELSE 1 END")
            ->orderBy('date_of_birth', 'desc')
            ->get();
    }

    public function getEnrolmentsForProfile(\App\Models\CompetitorProfile $profile)
    {
        return $profile->enrolments()
            ->with([
                'competition',
                'activeEvents.competitionEvent',
                'activeEvents.division',
                'activeEvents.result',
            ])
            ->orderByDesc('enrolled_at')
            ->get();
    }

}
