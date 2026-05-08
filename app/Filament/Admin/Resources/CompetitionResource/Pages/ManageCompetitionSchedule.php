<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use App\Models\Competition;
use App\Models\Division;
use Filament\Actions\Action;
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
            Action::make('events')
                ->label('Events')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('events', ['record' => $this->getRecord()])),

            Action::make('config')
                ->label('Configuration')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('config', ['record' => $this->getRecord()])),

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
            ->with('competitionEvent.eventType')
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
            ->with('eventType')
            ->get()
            ->pluck('eventType.name', 'eventType.id')
            ->unique()
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
        $competition = $this->getRecord();
        $division    = Division::with('competitionEvent')->findOrFail($divisionId);

        if ($division->competitionEvent->competition_id !== $competition->id) {
            return;
        }

        $locationLabel = $location === '__unassigned__' ? null : $location;
        $division->update(['location_label' => $locationLabel]);

        $siblings = Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->where('id', '!=', $divisionId)
            ->when($locationLabel, fn ($q) => $q->where('location_label', $locationLabel))
            ->when(! $locationLabel, fn ($q) => $q->whereNull('location_label'))
            ->orderBy('running_order')
            ->orderBy('code')
            ->get();

        $siblings->splice($newIndex, 0, [$division]);
        foreach ($siblings as $i => $sib) {
            $sib->updateQuietly(['running_order' => $i + 1]);
        }
    }

    public function cancelDivision(int $divisionId): void
    {
        $division = Division::with('competitionEvent')->findOrFail($divisionId);
        if ($division->competitionEvent->competition_id === $this->getRecord()->id) {
            $division->update(['status' => 'cancelled', 'location_label' => null]);
        }
    }

    public function reinstateDivision(int $divisionId): void
    {
        $division = Division::with('competitionEvent')->findOrFail($divisionId);
        if ($division->competitionEvent->competition_id === $this->getRecord()->id) {
            $division->update(['status' => 'pending']);
        }
    }
}
