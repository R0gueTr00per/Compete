<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use App\Filament\Admin\Resources\CompetitionResource\RelationManagers\AgeBandsRelationManager;
use App\Filament\Admin\Resources\CompetitionResource\RelationManagers\CompetitionEventsRelationManager;
use App\Filament\Admin\Resources\CompetitionResource\RelationManagers\RankBandsRelationManager;
use App\Filament\Admin\Resources\CompetitionResource\RelationManagers\WeightClassesRelationManager;
use App\Models\Division;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCompetition extends EditRecord
{
    protected static string $resource = CompetitionResource::class;

    public function getRelationManagers(): array
    {
        return [
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
        $locations = collect(array_values($this->data['locations'] ?? []))
            ->map(fn ($v) => strtolower(trim((string) (is_array($v) ? ($v['location'] ?? array_values($v)[0] ?? '') : $v))))
            ->filter();

        if ($locations->count() !== $locations->unique()->count()) {
            Notification::make()
                ->danger()
                ->title('Duplicate location')
                ->body('Each location must have a unique name.')
                ->send();
            $this->halt();
            return;
        }

        $newStatus = $this->data['status'] ?? null;

        if ($newStatus === 'open' && $this->record->status !== 'open') {
            $unscheduled = $this->record->allDivisions()
                ->whereNull('divisions.location_label')
                ->whereNotIn('divisions.status', ['combined'])
                ->count();

            if ($unscheduled > 0) {
                Notification::make()
                    ->warning()
                    ->title('Unscheduled divisions')
                    ->body("{$unscheduled} division(s) have not been assigned to a location. Assign them in the Scheduling screen before opening for enrolment.")
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
