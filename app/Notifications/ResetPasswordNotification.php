<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = route('filament.portal.auth.password-reset.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $message = (new MailMessage)
            ->subject('Reset your password')
            ->greeting("Hi {$notifiable->getFilamentName()},")
            ->line('You are receiving this email because a password reset request was made for your account.')
            ->line('Click the button below to reset your password. This link expires in ' . config('auth.passwords.users.expire', 60) . ' minutes.')
            ->action('Reset password', $resetUrl)
            ->line('If you did not request a password reset, no further action is required.');

        return EmailFooterHelper::append($message);
    }
}
