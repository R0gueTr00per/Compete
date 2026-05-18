<?php

namespace App\Filament\Admin\Resources\CompetitorResource\Pages;

use App\Filament\Admin\Resources\CompetitorResource;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class EditCompetitor extends EditRecord
{
    protected static string $resource = CompetitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->is_active)
                ->action(function () {
                    $this->record->update(['is_active' => false]);
                    Notification::make()->title('Profile deactivated.')->success()->send();
                }),

            Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn () => ! $this->record->is_active)
                ->action(function () {
                    $this->record->update(['is_active' => true]);
                    Notification::make()->title('Profile activated.')->success()->send();
                }),

            Action::make('promoteToOwnAccount')
                ->label('Promote to own account')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success')
                ->visible(fn () => $this->record->profile_type === 'child')
                ->modalHeading('Promote to own account')
                ->modalDescription('This will create a new login account for this competitor. They will receive an email with a link to set their password.')
                ->modalSubmitActionLabel('Create account')
                ->form([
                    TextInput::make('email')
                        ->label('Email address for new account')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique('users', 'email'),
                ])
                ->action(function (array $data) {
                    $newUser = User::create([
                        'email'    => $data['email'],
                        'password' => Hash::make(Str::random(32)),
                        'status'   => 'active',
                    ]);
                    $newUser->forceFill(['email_verified_at' => now()])->save();
                    $newUser->assignRole('user');

                    $this->record->update([
                        'profile_type' => 'self',
                        'user_id'       => $newUser->id,
                        'owner_user_id' => $newUser->id,
                    ]);

                    $token = Password::broker()->createToken($newUser);
                    $newUser->notify(new AccountCreatedNotification($token));

                    Notification::make()->title('Account created and setup email sent.')->success()->send();
                }),

            Action::make('resendSetupEmail')
                ->label('Resend account setup email')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('A new password setup link will be emailed to this competitor\'s account.')
                ->visible(fn () => $this->record->user_id !== null)
                ->action(function () {
                    $user = $this->record->account;
                    if (! $user) return;
                    $token = Password::broker()->createToken($user);
                    $user->notify(new AccountCreatedNotification($token));
                    Notification::make()->title('Account setup email sent.')->success()->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['profile_photo']) && is_array($data['profile_photo'])) {
            $data['profile_photo'] = array_values($data['profile_photo'])[0] ?? null;
        }

        $data['profile_complete'] = filled($data['first_name'] ?? null)
            && filled($data['surname'] ?? null)
            && filled($data['date_of_birth'] ?? null)
            && filled($data['gender'] ?? null);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
