<?php

namespace App\Filament\Portal\Pages\Auth;

use App\Models\User;
use App\Notifications\NewUserRegisteredNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;

class Register extends BaseRegister
{
    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(2);
        } catch (\Illuminate\Http\Exceptions\ThrottleRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return null;
        }

        $data           = $this->form->getState();
        $data['status'] = 'pending';

        try {
            $user = $this->wrapInDatabaseTransaction(fn () => $this->handleRegistration($data));
        } catch (UniqueConstraintViolationException) {
            $this->addError('data.email', 'An account already exists for this email address.');
            return null;
        }

        // Send outside the transaction so a mail failure does not roll back the account
        try {
            $sysAdmins = User::role('system_admin')->where('status', 'active')->get();
            if ($sysAdmins->isNotEmpty()) {
                Notification::send($sysAdmins, new NewUserRegisteredNotification($user));
            }
        } catch (\Throwable) {
            // Non-fatal — account is created regardless
        }

        // Do not auto-login — pending users cannot access the portal until approved
        $this->redirect(route('filament.portal.auth.login') . '?registered=1', navigate: true);

        return null;
    }

    protected function handleRegistration(array $data): Model
    {
        $firstName = $data['first_name'] ?? null;
        $lastName  = $data['last_name']  ?? null;
        $dob       = $data['date_of_birth'] ?? null;
        $gender    = $data['gender'] ?? null;

        unset($data['first_name'], $data['last_name'], $data['date_of_birth'], $data['gender']);

        $user = $this->getUserModel()::create($data);

        $user->competitorProfile()->create([
            'first_name'       => $firstName,
            'surname'          => $lastName,
            'date_of_birth'    => $dob,
            'gender'           => $gender,
            'profile_complete' => filled($firstName) && filled($lastName) && filled($dob) && filled($gender),
        ]);

        return $user;
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        TextInput::make('first_name')
                            ->label('First name')
                            ->required()
                            ->maxLength(100)
                            ->autofocus(),
                        TextInput::make('last_name')
                            ->label('Last name')
                            ->required()
                            ->maxLength(100),
                        $this->getEmailFormComponent()
                            ->validationMessages(['unique' => 'An account already exists for this email address.']),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        DatePicker::make('date_of_birth')
                            ->label('Date of birth')
                            ->required()
                            ->maxDate(now()->subYears(5)),
                        Radio::make('gender')
                            ->options(['M' => 'Male', 'F' => 'Female'])
                            ->required()
                            ->inline(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getRegisterFormAction(),
            Action::make('cancel')
                ->label(__('Cancel'))
                ->url(route('filament.portal.auth.login'))
                ->color('gray')
                ->outlined(),
        ];
    }
}
