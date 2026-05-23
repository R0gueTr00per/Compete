<?php

namespace App\Filament\Portal\Pages;

use App\Models\Enrolment;
use Filament\Pages\Page;

class MyEnrolmentsPage extends Page
{
    protected static ?string $title           = 'My Enrolments';
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'My Enrolments';
    protected static string $view = 'filament.portal.pages.my-enrolments-page';
    protected static ?string $slug = 'my-enrolments';
    protected static bool $shouldRegisterNavigation = false;

    public function getEnrolments()
    {
        $profileIds = auth()->user()->ownedProfiles()->pluck('id');

        return Enrolment::whereIn('competitor_profile_id', $profileIds)
            ->with([
                'competitor',
                'competition',
                'activeEvents.competitionEvent',
                'activeEvents.result.judgeScores',
                'activeEvents.division.activeEnrolmentEvents.enrolment.competitor',
            ])
            ->orderByDesc('enrolled_at')
            ->get();
    }
}
