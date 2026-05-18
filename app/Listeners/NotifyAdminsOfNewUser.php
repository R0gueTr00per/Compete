<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\NewUserRegisteredNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOfNewUser
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if ($user->status !== 'pending') {
            return;
        }

        $admins = User::role('system_admin')->where('status', 'active')->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewUserRegisteredNotification($user));
        }
    }
}
