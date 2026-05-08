<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use App\Models\Competition;
use App\Services\DivisionAssignmentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCompetition extends CreateRecord
{
    protected static string $resource = CompetitionResource::class;

    protected function afterCreate(): void
    {
        $latest = Competition::where('id', '!=', $this->record->id)
            ->orderByDesc('competition_date')
            ->first();

        if (! $latest) {
            return;
        }

        $service = app(DivisionAssignmentService::class);
        $service->copyDivisionsFromCompetition($latest, $this->record);

        Notification::make()
            ->title('Structure copied from "' . $latest->name . '"')
            ->body('Age bands, rank bands, weight classes, events, and all divisions have been copied. Review and adjust the Events and Age/Rank/Weight tabs before opening enrolments.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
