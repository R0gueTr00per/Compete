<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PortalVerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verifyUrl = URL::temporarySignedRoute(
            'portal.verify-email',
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
        );

        return (new MailMessage)
            ->subject('Verify your email address — Compete')
            ->greeting('Almost there!')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verifyUrl)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}
