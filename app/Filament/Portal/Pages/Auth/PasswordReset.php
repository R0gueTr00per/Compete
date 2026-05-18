<?php

namespace App\Filament\Portal\Pages\Auth;

use Filament\Pages\Auth\PasswordReset\ResetPassword as BaseResetPassword;

class PasswordReset extends BaseResetPassword
{
    protected function getRedirectUrl(): string
    {
        return route('filament.portal.auth.login');
    }
}
