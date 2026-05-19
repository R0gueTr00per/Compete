<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Notifications\AccountCreatedNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Password;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private string $role = 'user';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->role = $data['role'] ?? 'user';
        unset($data['role']);
        $data['status'] = 'pending';
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->role]);

        $token = Password::broker()->createToken($this->record);
        $this->record->notify(new AccountCreatedNotification($token));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
