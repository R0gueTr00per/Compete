<?php

namespace App\Filament\OrgAdmin\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function mount(): void
    {
        $query = request()->getQueryString();
        $this->redirect('/portal/login' . ($query ? '?' . $query : ''), navigate: false);
    }
}
