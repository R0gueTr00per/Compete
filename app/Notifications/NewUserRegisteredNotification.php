<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegisteredNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable;

    public function __construct(protected User $newUser) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->newUser->getFilamentName();

        return (new MailMessage)
            ->subject('Compete: New user awaiting approval')
            ->greeting('New user registration')
            ->line("**{$name}** has registered and is awaiting approval.")
            ->line('Email: ' . $this->newUser->email)
            ->line('They will need to create a competitor profile before they can enrol.')
            ->action('Review users', url(route('filament.admin.resources.users.index')));
    }
}
