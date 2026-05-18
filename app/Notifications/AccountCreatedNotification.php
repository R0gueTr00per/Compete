<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $resetToken) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->getFilamentName();

        $resetUrl = url(route('filament.portal.auth.password-reset.reset', [
            'token' => $this->resetToken,
            'email' => $notifiable->email,
        ]));

        return (new MailMessage)
            ->subject('Welcome to Compete — set your password')
            ->greeting("Hi {$name},")
            ->line('An account has been created for you on Compete.')
            ->line('Click the button below to set your password and access your account.')
            ->action('Set your password', $resetUrl);
    }
}
