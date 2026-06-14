<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;

use App\Filament\OrgAdmin\Resources\CompetitionResource;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\AgeBandsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\CompetitionEventsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\CompetitionMessagesRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\LocationsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\OfficialsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\RankBandsRelationManager;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers\WeightClassesRelationManager;
use App\Jobs\GenerateCompetitionInsightsJob;
use App\Jobs\GenerateEnrolmentSummariesJob;
use App\Jobs\SendCompetitionPromoEmailJob;
use App\Models\Division;
use App\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;

class EditCompetition extends EditRecord
{
    protected static string $resource = CompetitionResource::class;

    public bool $confirmedStatusChange = false;
    public ?string $statusBeforeSave = null;

    public function getRelationManagers(): array
    {
        return [
            CompetitionEventsRelationManager::class,
            AgeBandsRelationManager::class,
            RankBandsRelationManager::class,
            WeightClassesRelationManager::class,
            LocationsRelationManager::class,
            OfficialsRelationManager::class,
            CompetitionMessagesRelationManager::class,
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

            Action::make('publicPage')
                ->label('Public Page')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->modalHeading('Public Schedule & Results')
                ->modalContent(fn () => view(
                    'filament.admin.partials.public-schedule-modal',
                    [
                        'competition' => $this->record,
                        'url'         => $this->record->publicScheduleUrl(),
                    ]
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

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

            $missingTarget = $this->record->competitionEvents()
                ->whereNull('default_max_competitors')
                ->count();

            $missingTiming = $this->record->competitionEvents()
                ->whereNull('seconds_per_competitor')
                ->whereNull('round_duration_seconds')
                ->count();

            $this->mountAction('confirmOpenRegistrations', [
                'unscheduled'    => $unscheduled,
                'missing_target' => $missingTarget,
                'missing_timing' => $missingTiming,
            ]);
            $this->halt();
        }

        $this->confirmedStatusChange = false;
    }

    protected function afterSave(): void
    {
        if ($this->record->status === 'complete' && ! $this->record->completed_at) {
            $this->record->update(['completed_at' => now()]);
        }

        if ($this->statusBeforeSave !== null && $this->statusBeforeSave !== $this->record->status) {
            if ($this->record->organisation->insights_auto_refresh ?? true) {
                try {
                    GenerateCompetitionInsightsJob::dispatchFor($this->record->fresh());
                    Notification::make()
                        ->success()
                        ->title('AI insights refreshed')
                        ->send();
                } catch (\Throwable) {
                    Notification::make()
                        ->warning()
                        ->title('AI insights could not be generated')
                        ->body('You can refresh them manually from the Insights page.')
                        ->send();
                }
            }

            if ($this->record->status === 'complete') {
                try {
                    GenerateEnrolmentSummariesJob::dispatchFor($this->record->fresh());
                } catch (\Throwable) {
                    // Summaries are non-critical; silently skip on failure
                }
            }
        }
    }

    public function confirmOpenRegistrationsAction(): Action
    {
        return Action::make('confirmOpenRegistrations')
            ->modalHeading('Open registrations')
            ->modalDescription(function (array $arguments) {
                $warnings = [];
                if (($arguments['unscheduled'] ?? 0) > 0) {
                    $warnings[] = "{$arguments['unscheduled']} division(s) have not been assigned to a location.";
                }
                if (($arguments['missing_target'] ?? 0) > 0) {
                    $warnings[] = "{$arguments['missing_target']} event(s) are missing a competitor target — schedule times cannot be calculated.";
                }
                if (($arguments['missing_timing'] ?? 0) > 0) {
                    $warnings[] = "{$arguments['missing_timing']} event(s) are missing timing values — schedule times cannot be calculated.";
                }
                return $warnings ? implode(' ', $warnings) . ' Open for registration anyway?' : null;
            })
            ->form([
                Toggle::make('send_promo_email')
                    ->label('Send promotional email to eligible users')
                    ->helperText('Sends an email to all active users with profiles in this organisation who have not opted out.')
                    ->default(false),
            ])
            ->modalSubmitActionLabel('Open registrations')
            ->action(function (array $data) {
                $this->confirmedStatusChange = true;
                if ($data['send_promo_email'] ?? false) {
                    SendCompetitionPromoEmailJob::dispatch($this->record);
                }
                $this->save();
            });
    }

    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return parent::getCancelFormAction()
            ->url($this->getResource()::getUrl('index'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
