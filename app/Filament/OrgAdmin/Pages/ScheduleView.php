<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\Division;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ScheduleView extends Page
{
    protected static ?string $navigationIcon     = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup    = 'Competitions';
    protected static ?int    $navigationSort     = 10;
    protected static ?string $navigationLabel    = 'Schedule';
    protected static string  $view              = 'filament.admin.pages.schedule-view';
    protected static bool    $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    #[Url]
    public ?int $competition_id = null;

    public function mount(): void
    {
        if (! $this->competition_id) {
            $comp = Competition::whereIn('status', ['open', 'running'])
                ->where('organisation_id', app('tenant')?->id)
                ->orderBy('competition_date')
                ->first();
            if ($comp) {
                $this->competition_id = $comp->id;
            }
        }
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function getTitle(): string
    {
        $comp = $this->getCompetition();
        return $comp ? $comp->name . ' — Schedule' : 'Schedule';
    }

    #[Computed]
    public function getCompetition(): ?Competition
    {
        return $this->competition_id ? Competition::find($this->competition_id) : null;
    }

    public function getLocations(): array
    {
        $comp = $this->getCompetition();
        return $comp ? collect($comp->locations ?? [])->filter()->values()->toArray() : [];
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
                'activeEnrolmentEvents.enrolment.competitor',
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
