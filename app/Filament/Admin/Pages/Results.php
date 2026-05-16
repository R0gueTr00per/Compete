<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Enrolment;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Results extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-trophy';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $navigationLabel = 'Results';
    protected static string  $view            = 'filament.admin.pages.results';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['competition_administrator', 'system_admin', 'competition_official']) ?? false;
    }

    #[Url]
    public ?int $competition_id = null;

    #[Url]
    public bool $onlyPlacings = true;

    #[Url]
    public ?string $search = null;

    #[Url]
    public ?int $selectedEvent = null;

    #[Url]
    public ?string $selectedDojo = null;

    public function mount(): void
    {
        if (! $this->competition_id) {
            $competition = Competition::whereIn('status', ['running', 'complete'])
                ->orderByDesc('competition_date')
                ->first();

            if ($competition) {
                $this->competition_id = $competition->id;
            }
        }
    }

    public function updatedCompetitionId(): void
    {
        $this->selectedEvent = null;
        $this->selectedDojo  = null;
        $this->search        = null;
    }

    public function getCompetitions(): array
    {
        return Competition::whereNotIn('status', ['draft'])
            ->orderByDesc('competition_date')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getEventOptions(): array
    {
        if (! $this->competition_id) {
            return [];
        }

        return CompetitionEvent::where('competition_id', $this->competition_id)
            ->whereNotIn('status', ['combined'])
            ->orderBy('running_order')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getDojoOptions(): array
    {
        if (! $this->competition_id) {
            return [];
        }

        return Enrolment::where('competition_id', $this->competition_id)
            ->get(['dojo_type', 'dojo_name', 'guest_style'])
            ->map(fn ($e) => $e->dojo_type === 'guest' ? $e->guest_style : $e->dojo_name)
            ->filter()
            ->unique()
            ->sort()
            ->values()
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

        $search      = strtolower(trim($this->search ?? ''));
        $selectedDojo = $this->selectedDojo;

        $query = $competition->competitionEvents()
            ->with([
                'divisions'                                          => fn ($q) => $q->where('status', 'complete'),
                'divisions.enrolmentEvents'                          => fn ($q) => $q->where('removed', false),
                'divisions.enrolmentEvents.enrolment.competitor.competitorProfile',
                'divisions.enrolmentEvents.result.judgeScores',
            ])
            ->whereNotIn('status', ['combined'])
            ->orderBy('running_order');

        if ($this->selectedEvent) {
            $query->where('id', $this->selectedEvent);
        }

        $events = $query->get();

        return $events->map(function ($compEvent) use ($search, $selectedDojo) {
            $divisions = $compEvent->divisions->map(function ($division) use ($search, $selectedDojo) {
                $entries = $division->enrolmentEvents
                    ->sortBy(fn ($ee) => $ee->result?->placement ?? 999);

                if ($this->onlyPlacings) {
                    $entries = $entries->filter(
                        fn ($ee) => $ee->result?->placement && $ee->result->placement <= 3
                    );
                }

                if ($selectedDojo !== null && $selectedDojo !== '') {
                    $entries = $entries->filter(function ($ee) use ($selectedDojo) {
                        $dojo = $ee->enrolment->dojo_type === 'guest'
                            ? ($ee->enrolment->guest_style ?? '')
                            : ($ee->enrolment->dojo_name ?? '');
                        return $dojo === $selectedDojo;
                    });
                }

                if ($search !== '') {
                    $entries = $entries->filter(function ($ee) use ($search) {
                        $profile = $ee->enrolment->competitor?->competitorProfile;
                        $name = strtolower($profile
                            ? "{$profile->first_name} {$profile->surname}"
                            : ($ee->enrolment->competitor?->name ?? ''));
                        $dojo = strtolower($ee->enrolment->dojo_type === 'guest'
                            ? ($ee->enrolment->guest_style ?? '')
                            : ($ee->enrolment->dojo_name ?? ''));
                        return str_contains($name, $search) || str_contains($dojo, $search);
                    });
                }

                $division->setRelation('enrolmentEvents', $entries);
                return $division;
            })->filter(fn ($div) => $div->enrolmentEvents->isNotEmpty());

            $compEvent->setRelation('divisions', $divisions);
            return $compEvent;
        })->filter(fn ($event) => $event->divisions->isNotEmpty());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Open Results PDF')
                ->modalDescription('The PDF will reflect your current search and filter settings.')
                ->modalSubmitActionLabel('Open PDF')
                ->url(fn () => route('results.pdf', [
                    'competition_id' => $this->competition_id,
                    'only_placings'  => $this->onlyPlacings ? '1' : '0',
                    'search'         => $this->search,
                    'selected_event' => $this->selectedEvent,
                    'selected_dojo'  => $this->selectedDojo,
                ]))
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->competition_id),
        ];
    }
}
