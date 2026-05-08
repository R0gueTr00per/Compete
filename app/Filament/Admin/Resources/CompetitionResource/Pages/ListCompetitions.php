<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompetitions extends ListRecords
{
    protected static string $resource = CompetitionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
