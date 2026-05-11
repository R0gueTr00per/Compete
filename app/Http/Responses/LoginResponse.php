<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['system_admin', 'competition_administrator', 'competition_official'])) {
            return redirect()->to(filament()->getPanel('admin')->getUrl());
        }

        return redirect()->to(filament()->getPanel('portal')->getUrl());
    }
}
