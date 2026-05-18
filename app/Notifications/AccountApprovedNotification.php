<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApprovedNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->getFilamentName();

        return (new MailMessage)
            ->subject('Your Compete account is now active')
            ->greeting("Hi {$name},")
            ->line('Great news — your account has been approved and is now active.')
            ->line('You can now log in and enrol in competitions.')
            ->action('Log in now', url(route('filament.portal.auth.login')));
    }
}
