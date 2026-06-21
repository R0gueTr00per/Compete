<?php

namespace App\Filament\OrgAdmin\Resources\OrganisationNewsResource\Pages;

use App\Filament\OrgAdmin\Resources\OrganisationNewsResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganisationNews extends CreateRecord
{
    protected static string $resource = OrganisationNewsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = app('tenant')?->id;
        return $data;
    }
}
