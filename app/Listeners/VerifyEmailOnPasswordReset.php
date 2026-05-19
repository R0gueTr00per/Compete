<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;

class VerifyEmailOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
