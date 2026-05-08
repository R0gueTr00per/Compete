<?php

namespace App\Filament\Portal\Pages;

use App\Models\Enrolment;
use Filament\Pages\Page;

class MyEnrolmentsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'My Enrolments';
    protected static string $view = 'filament.portal.pages.my-enrolments-page';
    protected static ?string $slug = 'my-enrolments';
    protected static bool $shouldRegisterNavigation = false;

    public function getEnrolments()
    {
        return Enrolment::where('competitor_id', auth()->id())
            ->with([
                'competition',
                'activeEvents.competitionEvent.eventType',
                'activeEvents.division',
                'activeEvents.result.judgeScores',
                'activeEvents' => fn ($q) => $q->where('removed', false),
            ])
            ->orderByDesc('enrolled_at')
            ->get();
    }
}
