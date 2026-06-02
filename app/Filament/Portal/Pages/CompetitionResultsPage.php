<?php

namespace App\Filament\Portal\Pages;

use App\Models\CompetitorProfile;
use App\Models\Enrolment;
use Filament\Pages\Page;

class CompetitionResultsPage extends Page
{
    protected static ?string $title           = 'Results';
    protected static ?string $navigationIcon  = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Results';
    protected static string  $view            = 'filament.portal.pages.competition-results-page';
    protected static ?string $slug            = 'results';

    public function getHistory(): \Illuminate\Support\Collection
    {
        $orgId = app('tenant')?->id;

        $profileIds = CompetitorProfile::where('organisation_id', $orgId)
            ->where(fn ($q) => $q->where('owner_user_id', auth()->id())->orWhere('user_id', auth()->id()))
            ->pluck('id');

        if ($profileIds->isEmpty()) {
            return collect();
        }

        return Enrolment::whereIn('competitor_profile_id', $profileIds)
            ->whereHas('competition', fn ($q) => $q
                ->where('organisation_id', $orgId)
                ->whereIn('status', ['complete', 'running'])
            )
            ->with([
                'competition',
                'competitor',
                'activeEvents.competitionEvent',
                'activeEvents.division',
                'activeEvents.result',
            ])
            ->get()
            ->groupBy('competition_id')
            ->sortByDesc(fn ($enrolments) => $enrolments->first()->competition->competition_date);
    }
}
