<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class CompetitionResultsPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Results';
    protected static string  $view            = 'filament.portal.pages.competition-results-page';
    protected static ?string $slug            = 'results';

    #[Url]
    public ?int $competition_id = null;

    public function mount(): void
    {
        if (! $this->competition_id) {
            $competition = Competition::whereIn('status', ['complete', 'running'])
                ->orderByDesc('competition_date')
                ->first();

            if ($competition) {
                $this->competition_id = $competition->id;
            }
        }
    }

    public function getCompetitions(): array
    {
        return Competition::whereIn('status', ['complete', 'running'])
            ->orderByDesc('competition_date')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getResultsData(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) {
            return collect();
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition) {
            return collect();
        }

        return $competition->competitionEvents()
            ->with([
                'divisions' => fn ($q) => $q->whereNotIn('status', ['cancelled']),
                'divisions.enrolmentEvents' => fn ($q) => $q->where('removed', false),
                'divisions.enrolmentEvents.enrolment.competitor.competitorProfile',
                'divisions.enrolmentEvents.result',
            ])
            ->whereIn('status', ['running', 'complete'])
            ->orderBy('running_order')
            ->get()
            ->filter(fn ($event) => $event->divisions
                ->filter(fn ($div) => $div->enrolmentEvents->isNotEmpty())
                ->isNotEmpty()
            );
    }
}
