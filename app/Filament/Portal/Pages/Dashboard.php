<?php

namespace App\Filament\Portal\Pages;

use App\Models\Enrolment;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.portal.pages.dashboard';

    public function getProfile()
    {
        return auth()->user()->competitorProfile;
    }

    public function getEnrolments()
    {
        return Enrolment::where('competitor_id', auth()->id())
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
