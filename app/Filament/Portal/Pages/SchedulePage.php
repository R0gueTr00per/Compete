<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class SchedulePage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Schedule';
    protected static string  $view            = 'filament.portal.pages.schedule-page';
    protected static bool    $shouldRegisterNavigation = false;

    #[Url]
    public ?int $competition_id = null;

    public function mount(): void
    {
        if (! $this->competition_id) {
            $comp = Competition::whereIn('status', ['open', 'running'])
                ->orderBy('competition_date')
                ->first();
            if ($comp) {
                $this->competition_id = $comp->id;
            }
        }
    }

    public function getTitle(): string
    {
        $comp = $this->getCompetition();
        return $comp ? $comp->name . ' — Schedule' : 'Schedule';
    }

    public function getCompetition(): ?Competition
    {
        return $this->competition_id ? Competition::find($this->competition_id) : null;
    }

    public function getLocations(): array
    {
        $comp = $this->getCompetition();
        return $comp ? collect($comp->locations ?? [])->filter()->values()->toArray() : [];
    }

    public function getMyDivisionIds(): array
    {
        if (! $this->competition_id || ! Auth::check()) {
            return [];
        }

        return EnrolmentEvent::whereHas('enrolment', fn ($q) =>
                $q->where('competition_id', $this->competition_id)
                  ->where('competitor_id', Auth::id())
            )
            ->whereNotNull('division_id')
            ->where('removed', false)
            ->pluck('division_id')
            ->toArray();
    }

    public function getDivisions(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) {
            return collect();
        }

        return Division::whereHas('competitionEvent', fn ($q) =>
                $q->where('competition_id', $this->competition_id)
            )
            ->with([
                'competitionEvent',
                'activeEnrolmentEvents.result',
                'activeEnrolmentEvents.enrolment.competitor.competitorProfile',
            ])
            ->withCount('activeEnrolmentEvents')
            ->whereNotIn('status', ['combined'])
            ->whereNotNull('location_label')
            ->orderBy('running_order')
            ->orderBy('code')
            ->get()
            ->groupBy('location_label');
    }
}
