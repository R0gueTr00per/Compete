<?php

namespace App\Filament\Portal\Pages;

use App\Jobs\GenerateEnrolmentSummariesJob;
use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Enrolment;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class CompetitionResultsPage extends Page
{
    protected static ?string $title           = 'Results';
    protected static ?string $navigationIcon  = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Results';
    protected static string  $view            = 'filament.portal.pages.competition-results-page';
    protected static ?string $slug            = 'results';

    public function isSummaryGenerating(int $competitionId): bool
    {
        return (bool) Cache::get("summaries_generating_{$competitionId}");
    }

    public function triggerInsights(int $competitionId): void
    {
        if (! config('services.google_ai.api_key')) return;

        $competition = Competition::find($competitionId);
        if (! $competition) return;

        $key = "summaries_generating_{$competitionId}";
        if (Cache::get($key)) return;

        GenerateEnrolmentSummariesJob::dispatchFor($competition);
        Cache::put($key, true, now()->addMinutes(10));
    }

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
            ->filter(function ($enrolments) {
                $competition = $enrolments->first()->competition;
                if ($competition->status === 'complete') return true;
                // Running: only show if every scheduled (non-cancelled) active event has a result
                foreach ($enrolments as $enrolment) {
                    foreach ($enrolment->activeEvents as $event) {
                        if ($event->division?->location_label === null) continue;
                        if ($event->result === null) return false;
                    }
                }
                return $enrolments->flatMap->activeEvents->isNotEmpty();
            })
            ->sortByDesc(fn ($enrolments) => $enrolments->first()->competition->competition_date);
    }
}
