<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Notifications\AccountCreatedNotification;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Password;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resendSetupEmail')
                ->label('Resend account setup email')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('A new account setup link will be emailed to this user.')
                ->visible(fn () => auth()->user()?->hasRole('system_admin'))
                ->action(function () {
                    $token = Password::broker()->createToken($this->record);
                    $this->record->notify(new AccountCreatedNotification($token));
                    Notification::make()->title('Account setup email sent.')->success()->send();
                }),

            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasRole('system_admin') && auth()->id() !== $this->record->id)
                ->before(function () {
                    if ($this->record->enrolments()->exists()) {
                        Notification::make()
                            ->title('Cannot delete a user with enrolment history. Deactivate them instead.')
                            ->danger()
                            ->send();
                        $this->halt();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
