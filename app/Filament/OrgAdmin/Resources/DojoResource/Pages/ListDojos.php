<?php

namespace App\Filament\OrgAdmin\Resources\DojoResource\Pages;

use App\Filament\OrgAdmin\Resources\DojoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ListDojos extends ManageRecords
{
    protected static string $resource = DojoResource::class;

    public function getSubheading(): ?string
    {
        $name = strtolower(tenant_group_name());
        return "A {$name} cannot be deleted once it has been used in an enrolment — deactivate it instead to hide it from new enrolments.";
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['organisation_id'] = app('tenant')?->id;
                    return $data;
                }),
        ];
    }
}
