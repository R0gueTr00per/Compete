<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;

use App\Filament\OrgAdmin\Resources\CompetitionResource;
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
