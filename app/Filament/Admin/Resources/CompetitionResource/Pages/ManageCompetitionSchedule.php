<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use App\Models\Competition;
use App\Models\Division;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageCompetitionSchedule extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CompetitionResource::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static string $view = 'filament.admin.pages.scheduling-board';

    public ?int $filterEventType = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public static function getNavigationLabel(): string
    {
        return 'Scheduling';
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — Scheduling';
    }

    public function getBreadcrumb(): string
    {
        return 'Scheduling';
    }

    protected function getHeaderActions(): array
    {
        return [
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

                    $count = Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
                        ->whereDoesntHave('activeEnrolmentEvents')
                        ->whereNotIn('status', ['combined'])
                        ->where(fn ($q) => $q->whereNotNull('location_label')->orWhere('status', 'assigned'))
                        ->count();

                    if ($count === 0) {
                        Notification::make()->title('No empty assigned divisions found.')->warning()->send();
                        return;
                    }

                    Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
                        ->whereDoesntHave('activeEnrolmentEvents')
                        ->whereNotIn('status', ['combined'])
                        ->where(fn ($q) => $q->whereNotNull('location_label')->orWhere('status', 'assigned'))
                        ->update(['location_label' => null, 'status' => 'pending']);

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

        $divisions = Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->with('competitionEvent')
            ->withCount([
                'activeEnrolmentEvents',
                'activeEnrolmentEvents as checked_in_count' => fn ($q) => $q->whereHas('enrolment', fn ($q2) => $q2->where('checked_in', true)),
            ])
            ->orderBy('running_order')
            ->orderBy('code')
            ->get();

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

        // Unassigned always sorted by code
        usort($grouped['__unassigned__'], fn ($a, $b) => strcmp($a->code, $b->code));

        $eventTypes = $competition->competitionEvents()
            ->pluck('name', 'id')
            ->sort()
            ->toArray();

        return [
            'columns'           => $columns,
            'divisionsByColumn' => $grouped,
            'eventTypes'        => $eventTypes,
            'filterEventType'   => $this->filterEventType,
        ];
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

        $moved->each(fn ($d) => $d->update(['location_label' => $locationLabel]));

        $siblings = Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->whereNotIn('id', $divisionIds)
            ->when($locationLabel, fn ($q) => $q->where('location_label', $locationLabel))
            ->when(! $locationLabel, fn ($q) => $q->whereNull('location_label'))
            ->orderBy('running_order')
            ->orderBy('code')
            ->get();

        $siblings->splice($newIndex, 0, $moved->values()->all());
        foreach ($siblings as $i => $sib) {
            $sib->updateQuietly(['running_order' => $i + 1]);
        }
    }

}
