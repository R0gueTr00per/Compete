<?php

namespace App\Filament\OrgAdmin\Resources\OfficialRoleResource\Pages;

use App\Filament\OrgAdmin\Resources\OfficialRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ListOfficialRoles extends ManageRecords
{
    protected static string $resource = OfficialRoleResource::class;

    protected ?string $subheading = 'An official role cannot be deleted once it has been assigned to a competition official.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
