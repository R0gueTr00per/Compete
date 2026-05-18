<?php

namespace App\Filament\Portal\Pages\Auth;

use Filament\Actions\Action;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

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

        // Send verification email — admin notification fires only after the user verifies
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable) {
            // Non-fatal — account is created regardless
        }

        // Do not auto-login — pending users cannot access the portal until approved
        $this->redirect(route('filament.portal.auth.login') . '?registered=1', navigate: true);

        return null;
    }

    protected function handleRegistration(array $data): Model
    {
        return $this->getUserModel()::create($data);
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent()
                            ->autofocus()
                            ->validationMessages(['unique' => 'An account already exists for this email address.']),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
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
