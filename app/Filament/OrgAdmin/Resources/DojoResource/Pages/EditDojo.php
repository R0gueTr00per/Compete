<?php

namespace App\Filament\OrgAdmin\Resources\DojoResource\Pages;

use App\Filament\OrgAdmin\Resources\DojoResource;
use Filament\Resources\Pages\EditRecord;

class EditDojo extends EditRecord
{
    protected static string $resource = DojoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
