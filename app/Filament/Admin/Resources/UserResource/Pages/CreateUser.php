<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Notifications\AccountCreatedNotification;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status']             = 'active';
        $data['email_verified_at'] = Carbon::now();
        $data['password']          = Str::random(64);
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles(['system_admin']);

        $token = Password::broker()->createToken($this->record);
        $this->record->notify(new AccountCreatedNotification($token));

        Notification::make()
            ->title('User created')
            ->body("An account setup email has been sent to {$this->record->email}.")
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
