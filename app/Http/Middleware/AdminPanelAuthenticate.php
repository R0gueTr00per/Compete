<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

class AdminPanelAuthenticate extends Authenticate
{
    protected function redirectTo($request): string
    {
        return route('filament.portal.auth.login');
    }
}
