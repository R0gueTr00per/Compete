<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;

class ActivateUserOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if ($user->status === 'pending') {
            $user->forceFill(['status' => 'active'])->save();
        }
    }
}
