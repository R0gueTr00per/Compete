<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
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

    public function getActiveCompetitions()
    {
        return Competition::whereIn('status', ['open', 'enrolments_closed', 'check_in', 'running'])
            ->where('organisation_id', app('tenant')?->id)
            ->orderBy('competition_date')
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
            ->get()
            ->keyBy('competition_id');
    }

}
