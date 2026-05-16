<?php

namespace App\Filament\Portal\Pages\Auth;

use Filament\Actions\Action;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;

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

        $this->wrapInDatabaseTransaction(fn () => $this->handleRegistration($data));

        // Do not auto-login — pending users cannot access the portal until approved
        $this->redirect(route('filament.portal.auth.login') . '?registered=1', navigate: true);

        return null;
    }

    protected function handleRegistration(array $data): Model
    {
        return $this->getUserModel()::create($data);
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
