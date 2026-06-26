<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;

use App\Filament\OrgAdmin\Resources\CompetitionResource;
use App\Models\CompetitionDay;
use App\Models\Division;
use App\Services\ScheduleCalculatorService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use App\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;

class ManageCompetitionSchedule extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CompetitionResource::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    protected string $view = 'filament.admin.pages.scheduling-board';

    public ?int $filterEventType = null;
    public bool $unassignedCollapsed = false;
    public ?int $selectedDayId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $competition  = $this->getRecord();

        $this->selectedDayId = $competition->competitionDays()->orderBy('date')->value('id');

        app(ScheduleCalculatorService::class)->recalculateAll($competition);
    }

    public static function getNavigationLabel(): string
    {
        return 'Scheduling';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — Scheduling';
    }

    public function getBreadcrumb(): string
    {
        return 'Scheduling';
    }

    public function setDay(int $dayId): void
    {
        $competition = $this->getRecord();
        if ($competition->competitionDays()->where('id', $dayId)->exists()) {
            $this->selectedDayId = $dayId;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate')
                ->label('Recalculate')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    app(ScheduleCalculatorService::class)->recalculateAll($this->getRecord());
                    Notification::make()->success()->title('Schedule recalculated.')->send();
                }),

            Action::make('manageBreaks')
                ->label('Breaks')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->modalHeading('Competition Breaks')
                ->modalDescription(function () {
                    $day = $this->selectedDayId
                        ? CompetitionDay::find($this->selectedDayId)
                        : null;
                    $label = $day ? ' (' . $day->date->format('D j M') . ')' : '';
                    return 'Breaks for this day apply to all mats. Events are automatically scheduled around them.' . $label;
                })
                ->modalWidth(Width::TwoExtraLarge)
                ->fillForm(function () {
                    $breaks = $this->selectedDayId
                        ? CompetitionDay::find($this->selectedDayId)?->breaks ?? collect()
                        : collect();
                    return [
                        'breaks' => $breaks->map(fn ($b) => [
                            'name'             => $b->name,
                            'start_time'       => \Carbon\Carbon::parse($b->start_time)->format('H:i'),
                            'duration_minutes' => $b->duration_minutes,
                        ])->toArray(),
                    ];
                })
                ->form([
                    Repeater::make('breaks')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('name')
                                ->label('Break name')
                                ->required()
                                ->placeholder('e.g. Lunch Break')
                                ->columnSpan(2),

                            TimePicker::make('start_time')
                                ->label('Start time')
                                ->required()
                                ->seconds(false),

                            TextInput::make('duration_minutes')
                                ->label('Duration')
                                ->required()
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->suffix('min'),
                        ])
                        ->columns(4)
                        ->addActionLabel('Add break')
                        ->defaultItems(0)
                        ->reorderable(false),
                ])
                ->action(function (array $data) {
                    $competition = $this->getRecord();

                    if (! $this->selectedDayId) {
                        Notification::make()->danger()->title('No day selected.')->send();
                        return;
                    }

                    $day = CompetitionDay::find($this->selectedDayId);
                    if (! $day) {
                        Notification::make()->danger()->title('Day not found.')->send();
                        return;
                    }

                    $day->breaks()->delete();
                    foreach ($data['breaks'] as $break) {
                        $day->breaks()->create(array_merge($break, [
                            'competition_id' => $competition->id,
                        ]));
                    }

                    app(ScheduleCalculatorService::class)->recalculateAll($competition->fresh());
                    Notification::make()->success()->title('Breaks saved.')->send();
                }),

            Action::make('unassignEmpty')
                ->label('Unassign empty divisions')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Unassign empty divisions')
                ->modalDescription('All assigned divisions with no enrolments will have their location cleared and status reset to pending.')
                ->modalSubmitActionLabel('Yes, unassign them')
                ->action(function () {
                    $competition = $this->getRecord();

                    $query = Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
                        ->whereDoesntHave('activeEnrolmentEvents')
                        ->whereNotIn('status', ['combined'])
                        ->where(fn ($q) => $q->whereNotNull('location_label')->orWhere('status', 'assigned'));

                    if ($this->selectedDayId) {
                        $query->where('competition_day_id', $this->selectedDayId);
                    }

                    $count = $query->count();

                    if ($count === 0) {
                        Notification::make()->title('No empty assigned divisions found.')->warning()->send();
                        return;
                    }

                    $query->update(['location_label' => null, 'status' => 'pending']);

                    Notification::make()->success()->title("{$count} empty division(s) unassigned.")->send();
                }),

            Action::make('events')
                ->label('Events')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('events', ['record' => $this->getRecord()])),

            Action::make('back')
                ->label('Back to competition')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    protected function getViewData(): array
    {
        $competition = $this->getRecord();

        $columns = collect($competition->locations ?? [])
            ->filter()
            ->values()
            ->toArray();

        $selectedDay = $this->selectedDayId
            ? $competition->competitionDays()->find($this->selectedDayId)
            : $competition->competitionDays()->orderBy('date')->first();

        $divisionsQuery = Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->with('competitionEvent')
            ->withCount([
                'activeEnrolmentEvents',
                'activeEnrolmentEvents as checked_in_count' => fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('checked_in', true)),
            ])
            ->orderBy('running_order')
            ->orderBy('code');

        if ($selectedDay) {
            // No location = unassigned pool (global, any day); with location = selected day only
            $divisionsQuery->where(function ($q) use ($selectedDay) {
                $q->whereNull('location_label')
                  ->orWhere('competition_day_id', $selectedDay->id);
            });
        }

        $divisions = $divisionsQuery->get();

        $grouped = ['__unassigned__' => []];
        foreach ($columns as $col) {
            $grouped[$col] = [];
        }
        foreach ($divisions as $div) {
            $key = $div->location_label ?? '__unassigned__';
            if (! array_key_exists($key, $grouped)) {
                $key = '__unassigned__';
            }
            $grouped[$key][] = $div;
        }


        $eventTypes = $competition->competitionEvents()
            ->pluck('name', 'id')
            ->sort()
            ->toArray();

        $missingTarget = $competition->competitionEvents()
            ->whereNull('default_max_competitors')
            ->pluck('name')
            ->sort()
            ->values()
            ->toArray();

        $breaks = $selectedDay ? $selectedDay->breaks : collect();

        $competitionDays = $competition->competitionDays()->orderBy('date')->get();

        return [
            'columns'              => $columns,
            'divisionsByColumn'    => $grouped,
            'eventTypes'           => $eventTypes,
            'filterEventType'      => $this->filterEventType,
            'missingTarget'        => $missingTarget,
            'breaks'               => $breaks,
            'unassignedCollapsed'  => $this->unassignedCollapsed,
            'selectedDay'          => $selectedDay,
            'competitionDays'      => $competitionDays,
        ];
    }

    public function performMerge(array $divisionIds): void
    {
        $competition = $this->getRecord();

        $divisions = Division::with('competitionEvent')
            ->whereIn('id', $divisionIds)
            ->whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->orderBy('id')
            ->get();

        if ($divisions->count() < 2) {
            Notification::make()->title('Select at least 2 divisions to merge.')->warning()->send();
            return;
        }

        if ($divisions->pluck('competition_event_id')->unique()->count() > 1) {
            Notification::make()->title('All selected divisions must be the same event type.')->warning()->send();
            return;
        }

        $primary = $divisions->first();
        $others  = $divisions->slice(1);

        foreach ($others as $division) {
            $division->activeEnrolmentEvents()->update(['division_id' => $primary->id]);
            $division->update([
                'status'           => 'combined',
                'combined_into_id' => $primary->id,
            ]);
        }

        $mergedCodes = $others->pluck('code')->filter()->join('/');
        if ($mergedCodes) {
            $primary->update(['label' => $primary->label . " (Merged with {$mergedCodes})"]);
        }

        Notification::make()->title('Divisions merged.')->success()->send();
    }

    public function moveDivision(int $divisionId, string $location, int $newIndex): void
    {
        $this->moveDivisions([$divisionId], $location, $newIndex);
    }

    public function moveDivisions(array $divisionIds, string $location, int $newIndex): void
    {
        $competition   = $this->getRecord();
        $locationLabel = $location === '__unassigned__' ? null : $location;

        $moved = Division::with('competitionEvent')
            ->whereIn('id', $divisionIds)
            ->get()
            ->filter(fn ($d) => $d->competitionEvent->competition_id === $competition->id);

        if ($moved->isEmpty()) {
            return;
        }

        $moved->each(fn ($d) => $d->update(
            $locationLabel === null
                ? ['location_label' => null, 'competition_day_id' => null, 'planned_start_at' => null, 'actual_start_at' => null, 'actual_end_at' => null]
                : ['location_label' => $locationLabel, 'competition_day_id' => $this->selectedDayId]
        ));

        $siblingsQuery = Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->whereNotIn('id', $divisionIds)
            ->when($locationLabel, fn ($q) => $q->where('location_label', $locationLabel))
            ->when(! $locationLabel, fn ($q) => $q->whereNull('location_label'))
            ->orderBy('running_order')
            ->orderBy('code');

        // Location columns are day-scoped; unassigned is global across all days
        if ($this->selectedDayId && $locationLabel) {
            $siblingsQuery->where('competition_day_id', $this->selectedDayId);
        }

        $siblings = $siblingsQuery->get();

        $siblings->splice($newIndex, 0, $moved->values()->all());
        foreach ($siblings as $i => $sib) {
            $sib->updateQuietly(['running_order' => $i + 1]);
        }

        if ($locationLabel) {
            app(ScheduleCalculatorService::class)->recalculateForLocation($competition, $locationLabel);
        }
    }
}
