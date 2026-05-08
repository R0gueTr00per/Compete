<?php

namespace App\Filament\Admin\Resources\CompetitionEventResource\Pages;

use App\Filament\Admin\Resources\CompetitionEventResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditCompetitionEvent extends EditRecord
{
    protected static string $resource = CompetitionEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to competition')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => route(
                    'filament.admin.resources.competitions.edit',
                    ['record' => $this->record->competition_id]
                )),
        ];
    }
}
