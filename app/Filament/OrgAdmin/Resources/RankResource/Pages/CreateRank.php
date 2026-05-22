<?php

namespace App\Filament\OrgAdmin\Resources\RankResource\Pages;

use App\Filament\OrgAdmin\Resources\RankResource;
use App\Models\Rank;
use Filament\Resources\Pages\CreateRecord;

class CreateRank extends CreateRecord
{
    protected static string $resource = RankResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $orgId = app('tenant')?->id;
        $data['organisation_id'] = $orgId;
        $data['sort_order'] = (Rank::where('organisation_id', $orgId)->max('sort_order') ?? 0) + 1;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
