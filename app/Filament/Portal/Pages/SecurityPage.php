<?php

namespace App\Filament\Portal\Pages;

use App\Notifications\PendingEmailVerificationNotification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class SecurityPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'Security';
    protected static string | \UnitEnum | null $navigationGroup = 'Account';
    protected static ?int    $navigationSort  = 185;
    protected string $view            = 'filament.portal.pages.security-page';
    protected static ?string $slug            = 'security';

    public ?array $emailData    = [];
    public ?array $passwordData = [];

    public function mount(): void
    {
        if (session()->pull('email_change_verified')) {
            Notification::make()->title('Email address updated.')->success()->send();
        }

        if ($error = session()->pull('email_change_error')) {
            Notification::make()->title($error)->danger()->send();
        }

        $this->emailForm->fill([
            'email' => auth()->user()->email,
        ]);

        $this->passwordForm->fill([]);
    }

    public function emailForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Change email')
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: 'users',
                                column: 'email',
                                ignorable: fn () => auth()->user(),
                                modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) => $rule->where('organisation_id', auth()->user()->organisation_id),
                            )
                            ->validationMessages(['unique' => 'This email address is already in use.']),
                    ]),
            ])
            ->statePath('emailData');
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Change password')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword(),

                        TextInput::make('password')
                            ->label('New password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),

                        TextInput::make('password_confirmation')
                            ->label('Confirm new password')
                            ->password()
                            ->revealable()
                            ->required(),
                    ]),
            ])
            ->statePath('passwordData');
    }

    protected function getForms(): array
    {
        return ['emailForm', 'passwordForm'];
    }

    public function requestEmailChange(): void
    {
        $data = $this->emailForm->getState();
        $user = auth()->user();

        $user->update(['pending_email' => $data['email']]);

        NotificationFacade::route('mail', $user->pending_email)
            ->notify(new PendingEmailVerificationNotification($user));

        Notification::make()
            ->title('Verification email sent to ' . $user->pending_email . '.')
            ->success()
            ->send();
    }

    public function cancelEmailChange(): void
    {
        auth()->user()->update(['pending_email' => null]);

        Notification::make()->title('Email change cancelled.')->success()->send();
    }

    public function resendEmailVerification(): void
    {
        $user = auth()->user();

        if (! $user->pending_email) {
            return;
        }

        NotificationFacade::route('mail', $user->pending_email)
            ->notify(new PendingEmailVerificationNotification($user));

        Notification::make()->title('Verification email resent.')->success()->send();
    }

    public function savePassword(): void
    {
        $data = $this->passwordForm->getState();

        auth()->user()->update(['password' => $data['password']]);

        $this->passwordForm->fill([]);

        Notification::make()->title('Password changed.')->success()->send();
    }

    public function getTitle(): string
    {
        return 'Security';
    }
}
