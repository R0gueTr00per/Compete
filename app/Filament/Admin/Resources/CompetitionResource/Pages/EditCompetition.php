<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use App\Models\Division;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCompetition extends EditRecord
{
    protected static string $resource = CompetitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('config')
                ->label('Configuration')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('config', ['record' => $this->record])),

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

            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $newStatus = $this->data['status'] ?? null;

        if ($newStatus === 'open' && $this->record->status !== 'open') {
            $unscheduled = $this->record->allDivisions()
                ->whereNull('divisions.location_label')
                ->where('divisions.status', '!=', 'cancelled')
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
