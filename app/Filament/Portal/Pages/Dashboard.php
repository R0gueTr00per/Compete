<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\Dojo;
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

    public function getInstructorDojos()
    {
        return auth()->user()->instructorOf()->with('instructor')->get();
    }

    public function getInstructorCompetitions()
    {
        $dojos = $this->getInstructorDojos();
        if ($dojos->isEmpty()) {
            return collect();
        }

        $dojoNames = $dojos->pluck('name');

        return Competition::whereIn('status', ['open', 'closed', 'check_in', 'running'])
            ->whereHas('enrolments', fn ($q) => $q->whereIn('dojo_name', $dojoNames))
            ->with([
                'enrolments' => fn ($q) => $q->whereIn('dojo_name', $dojoNames)
                    ->with(['competitor.competitorProfile', 'activeEvents.competitionEvent', 'activeEvents.division', 'activeEvents.result']),
            ])
            ->orderBy('competition_date')
            ->get();
    }
}
