<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;

use App\Filament\OrgAdmin\Resources\CompetitionResource;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\AgeBandsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\CompetitionEventsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\LocationsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\OfficialsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\RankBandsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\WeightClassesRelationManager;
use App\Jobs\GenerateCompetitionInsightsJob;
use App\Models\Division;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditCompetition extends EditRecord
{
    protected static string $resource = CompetitionResource::class;

    public bool $confirmedStatusChange = false;
    public ?string $statusBeforeSave = null;

    public function getRelationManagers(): array
    {
        return [
            LocationsRelationManager::class,
            OfficialsRelationManager::class,
            CompetitionEventsRelationManager::class,
            AgeBandsRelationManager::class,
            RankBandsRelationManager::class,
            WeightClassesRelationManager::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('events')
                ->label('Events')
                ->icon('heroicon-o-rectangle-stack')
                ->color('info')
                ->url(fn () => CompetitionResource::getUrl('events', ['record' => $this->record])),

            Action::make('schedule')
                ->label('Scheduling')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->url(fn () => CompetitionResource::getUrl('schedule', ['record' => $this->record])),

            Action::make('officials')
                ->label('Officials')
                ->icon('heroicon-o-identification')
                ->color('info')
                ->url(fn () => CompetitionResource::getUrl('officials', ['record' => $this->record])),

            Action::make('downloadPdf')
                ->label('Download results PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn () => $this->record->status === 'complete')
                ->action(function () {
                    $pdf = app(\App\Services\PdfReportService::class)
                        ->generateCompetitionResults($this->record);
                    $filename = str($this->record->name)->slug() . '-results.pdf';
                    return response()->streamDownload(fn () => print($pdf), $filename, [
                        'Content-Type' => 'application/pdf',
                    ]);
                }),

            Action::make('tasks')
                ->label(function () {
                    $pending = $this->record->tasks()->where('completed', false)->count();
                    return 'Tasks' . ($pending > 0 ? " ({$pending})" : '');
                })
                ->icon('heroicon-o-clipboard-document-check')
                ->color(fn () => $this->record->tasks()->where('completed', false)->exists() ? 'warning' : 'gray')
                ->url(fn () => CompetitionResource::getUrl('tasks', ['record' => $this->record])),

            Action::make('insights')
                ->label('AI Insights')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn () => true)
                ->url(fn () => CompetitionResource::getUrl('insights', ['record' => $this->record])),

            Action::make('history')
                ->label('History')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalHeading('Change History')
                ->modalContent(fn () => view(
                    'filament.admin.partials.history-modal',
                    ['activities' => \Spatie\Activitylog\Models\Activity::forSubject($this->record)->with('causer')->latest()->get()]
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

        ];
    }

    protected function beforeSave(): void
    {
        $this->statusBeforeSave = $this->record->status;
        $newStatus = $this->data['status'] ?? null;

        if ($newStatus === 'open' && $this->record->status !== 'open' && ! $this->confirmedStatusChange) {
            $unscheduled = $this->record->allDivisions()
                ->whereNull('divisions.location_label')
                ->whereNotIn('divisions.status', ['combined'])
                ->count();

            if ($unscheduled > 0) {
                $this->mountAction('confirmOpenWithUnscheduled', ['count' => $unscheduled]);
                $this->halt();
            }
        }

        $this->confirmedStatusChange = false;
    }

    protected function afterSave(): void
    {
        if ($this->statusBeforeSave !== null && $this->statusBeforeSave !== $this->record->status) {
            GenerateCompetitionInsightsJob::dispatchFor($this->record->fresh());
        }
    }

    public function confirmOpenWithUnscheduledAction(): Action
    {
        return Action::make('confirmOpenWithUnscheduled')
            ->modalHeading('Unscheduled divisions')
            ->modalDescription(fn (array $arguments) =>
                "{$arguments['count']} division(s) have not been assigned to a location. Open for enrolment anyway?"
            )
            ->modalSubmitActionLabel('Open anyway')
            ->action(function () {
                $this->confirmedStatusChange = true;
                $this->save();
            });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
