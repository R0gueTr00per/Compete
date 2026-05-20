<?php

namespace App\Filament\Admin\Resources\OrganisationResource\Pages;

use App\Filament\Admin\Resources\OrganisationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganisation extends ViewRecord
{
    protected static string $resource = OrganisationResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
