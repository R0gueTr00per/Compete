<?php

namespace App\Filament\Admin\Resources\DojoResource\Pages;

use App\Filament\Admin\Resources\DojoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ListDojos extends ManageRecords
{
    protected static string $resource = DojoResource::class;

    protected ?string $subheading = 'A dojo cannot be deleted once it has been used in an enrolment — deactivate it instead to hide it from new enrolments.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
