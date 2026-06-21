<?php

namespace App\Filament\OrgAdmin\Resources\OrganisationNewsResource\Pages;

use App\Filament\OrgAdmin\Resources\OrganisationNewsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganisationNews extends EditRecord
{
    protected static string $resource = OrganisationNewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
