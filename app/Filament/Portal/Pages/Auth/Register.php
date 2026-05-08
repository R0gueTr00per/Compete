<?php

namespace App\Filament\Portal\Pages\Auth;

use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    protected function handleRegistration(array $data): Model
    {
        $data['status'] = 'pending';

        return $this->getUserModel()::create($data);
    }

    protected function getRedirectUrl(): string
    {
        return route('filament.portal.auth.login') . '?registered=1';
    }
}
