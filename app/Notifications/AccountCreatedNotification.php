<?php

namespace App\Notifications;

use App\Models\Organisation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $resetToken,
        public readonly ?Organisation $org = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name    = $notifiable->getFilamentName();
        $orgName = $this->org?->name;

        $resetUrl = \Illuminate\Support\Facades\URL::signedRoute('filament.portal.auth.password-reset.reset', [
            'token' => $this->resetToken,
            'email' => $notifiable->email,
        ]);

        $subject = $orgName
            ? "Welcome to {$orgName} — set your password"
            : 'Welcome to Compete — set your password';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hi {$name},");

        if ($orgName) {
            $message->line("An account has been created for you on **{$orgName}**.");
        } else {
            $message->line('An account has been created for you on Compete.');
        }

        return $message
            ->line('Click the button below to set your password and access your account.')
            ->action('Set your password', $resetUrl);
    }
}
