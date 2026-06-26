<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PendingEmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private User $user)
    {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verifyUrl = URL::temporarySignedRoute(
            'portal.verify-email-change',
            now()->addHours(24),
            ['id' => $this->user->getKey(), 'hash' => sha1($this->user->pending_email)],
        );

        $message = (new MailMessage)
            ->subject('Confirm your new email address')
            ->greeting('Confirm your email change')
            ->line('You requested to change your login email address to **' . $this->user->pending_email . '**.')
            ->line('Click the button below to confirm this change.')
            ->action('Confirm Email Change', $verifyUrl)
            ->line('This link expires in 24 hours.')
            ->line('If you did not request this change, you can safely ignore this email — your current address will remain unchanged.');

        return EmailFooterHelper::append($message);
    }
}
