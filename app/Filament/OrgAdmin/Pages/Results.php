<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\CompetitionDay;
use App\Models\CompetitionEvent;
use App\Models\Enrolment;
use App\Models\Result;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class Results extends Page
{
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-trophy';
    protected static string | \UnitEnum | null $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 6;
    protected static ?string $navigationLabel = 'Results';
    protected string $view            = 'filament.admin.pages.results';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        $user = auth()->user();
        if ($user?->isOrgAdmin($tenant)) return true;
        return $user?->getActiveOfficialRoleFor($tenant)?->can_access_results ?? false;
    }

    #[Url]
    public ?int $competition_id = null;

    #[Url]
    public string $activeView = 'events';

    #[Url]
    public bool $onlyPlacings = true;

    #[Url]
    public ?string $search = null;

    #[Url]
    public ?int $selectedEvent = null;

    #[Url]
    public ?string $selectedDojo = null;

    #[Url]
    public ?string $selectedDay = null;

    public function mount(): void
    {
        if (! $this->competition_id) {
            $competition = Competition::whereIn('status', ['running', 'complete'])
                ->where('organisation_id', app('tenant')?->id)
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
        $this->selectedDay   = null;
    }

    #[Computed]
    public function getCompetitionDays(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) {
            return collect();
        }

        return CompetitionDay::where('competition_id', $this->competition_id)
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function getMedalTallyByCompetitor(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) {
            return collect();
        }

        $results = Result::whereHas('enrolmentEvent.enrolment', fn ($q) => $q->where('competition_id', $this->competition_id))
            ->when($this->selectedDay, fn ($q) => $q->whereHas('enrolmentEvent.division', fn ($q2) => $q2->where('competition_day_id', $this->selectedDay)))
            ->whereNotNull('placement')
            ->whereBetween('placement', [1, 3])
            ->where('disqualified', false)
            ->with('enrolmentEvent.enrolment.competitor')
            ->get();

        $tally = $results->groupBy(fn ($r) => $r->enrolmentEvent->enrolment->competitor_profile_id)
            ->map(function ($group) {
                $first      = $group->first();
                $competitor = $first->enrolmentEvent->enrolment->competitor;
                $enrolment  = $first->enrolmentEvent->enrolment;
                $dojo       = $enrolment->dojo_type === 'guest'
                    ? ($enrolment->guest_style ?? 'Guest')
                    : ($enrolment->dojo_name ?? '—');

                return [
                    'name'   => $competitor?->full_name ?? '—',
                    'dojo'   => $dojo,
                    'gold'   => $group->where('placement', 1)->count(),
                    'silver' => $group->where('placement', 2)->count(),
                    'bronze' => $group->where('placement', 3)->count(),
                ];
            })
            ->sortByDesc(fn ($t) => [$t['gold'], $t['silver'], $t['bronze']])
            ->values();

        return $this->assignRanks($tally);
    }

    #[Computed]
    public function getMedalTallyByDojo(): \Illuminate\Support\Collection
    {
        if (! $this->competition_id) {
            return collect();
        }

        $results = Result::whereHas('enrolmentEvent.enrolment', fn ($q) => $q->where('competition_id', $this->competition_id))
            ->when($this->selectedDay, fn ($q) => $q->whereHas('enrolmentEvent.division', fn ($q2) => $q2->where('competition_day_id', $this->selectedDay)))
            ->whereNotNull('placement')
            ->whereBetween('placement', [1, 3])
            ->where('disqualified', false)
            ->with('enrolmentEvent.enrolment')
            ->get();

        $tally = $results->groupBy(function ($r) {
                $enrolment = $r->enrolmentEvent->enrolment;
                return $enrolment->dojo_type === 'guest'
                    ? ($enrolment->guest_style ?? 'Guest')
                    : ($enrolment->dojo_name ?? '—');
            })
            ->map(function ($group, $dojoName) {
                return [
                    'name'   => $dojoName,
                    'gold'   => $group->where('placement', 1)->count(),
                    'silver' => $group->where('placement', 2)->count(),
                    'bronze' => $group->where('placement', 3)->count(),
                ];
            })
            ->sortByDesc(fn ($t) => [$t['gold'], $t['silver'], $t['bronze']])
            ->values();

        return $this->assignRanks($tally);
    }

    private function assignRanks(\Illuminate\Support\Collection $tally): \Illuminate\Support\Collection
    {
        $rank = 1;
        $prev = null;

        return $tally->map(function ($entry) use (&$rank, &$prev) {
            $key = [$entry['gold'], $entry['silver'], $entry['bronze']];
            if ($prev !== null && $key !== $prev) {
                $rank++;
            }
            $entry['rank'] = $rank;
            $prev          = $key;
            return $entry;
        });
    }

    #[Computed]
    public function getCompetitions(): array
    {
        return Competition::whereNotIn('status', ['planning', 'advertise'])
            ->where('organisation_id', app('tenant')?->id)
            ->orderByDesc('competition_date')
            ->pluck('name', 'id')
            ->toArray();
    }

    #[Computed]
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

    #[Computed]
    public function getDojoOptions(): array
    {
        if (! $this->competition_id) {
            return [];
        }

        return Enrolment::where('competition_id', $this->competition_id)
            ->select('dojo_type', 'dojo_name', 'guest_style')
            ->distinct()
            ->get()
            ->map(fn ($e) => $e->dojo_type === 'guest' ? $e->guest_style : $e->dojo_name)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    #[Computed]
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
                'divisions' => fn ($q) => $q->where('status', 'complete')
                    ->when($this->selectedDay, fn ($q) => $q->where('competition_day_id', $this->selectedDay)),
                'divisions.enrolmentEvents'                          => fn ($q) => $q->where('removed', false),
                'divisions.enrolmentEvents.enrolment.competitor',
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
                        fn ($ee) => $ee->result?->placement && $ee->result->placement <= 3 && ! $ee->result->disqualified
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
                        $name = strtolower($ee->enrolment->competitor?->full_name ?? '');
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
                ->modalDescription('The PDF will reflect your current view and filter settings.')
                ->modalSubmitActionLabel('Open PDF')
                ->url(fn () => match ($this->activeView) {
                    'by-competitor' => route('results.pdf.medal-tally-competitor', ['competition_id' => $this->competition_id]),
                    'by-dojo'       => route('results.pdf.medal-tally-dojo', ['competition_id' => $this->competition_id]),
                    default         => route('results.pdf', [
                        'competition_id' => $this->competition_id,
                        'only_placings'  => $this->onlyPlacings ? '1' : '0',
                        'search'         => $this->search,
                        'selected_event' => $this->selectedEvent,
                        'selected_dojo'  => $this->selectedDojo,
                    ]),
                })
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->competition_id),
        ];
    }
}
