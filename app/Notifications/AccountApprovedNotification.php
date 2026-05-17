<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $profile = $notifiable->competitorProfile;
        $name    = $profile
            ? trim($profile->first_name . ' ' . $profile->surname)
            : $notifiable->email;

        return (new MailMessage)
            ->subject('Your Compete account is now active')
            ->greeting("Hi {$name},")
            ->line('Great news — your account has been approved and is now active.')
            ->line('You can now log in and enrol in competitions.')
            ->action('Log in now', url(route('filament.portal.auth.login')));
    }
}
