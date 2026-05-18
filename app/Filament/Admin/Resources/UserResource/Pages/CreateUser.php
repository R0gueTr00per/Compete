<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private string $role = 'user';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->role = $data['role'] ?? 'user';
        unset($data['role']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->role]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
