<?php

namespace App\Filament\OrgAdmin\Resources\OrganisationNewsResource\Pages;

use App\Filament\OrgAdmin\Resources\OrganisationNewsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganisationNews extends ListRecords
{
    protected static string $resource = OrganisationNewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
