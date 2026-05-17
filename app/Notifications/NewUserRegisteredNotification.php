<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(protected User $newUser) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $profile = $this->newUser->competitorProfile;
        $name    = $profile
            ? trim($profile->first_name . ' ' . $profile->surname)
            : $this->newUser->email;

        return (new MailMessage)
            ->subject('Compete: New user awaiting approval')
            ->greeting('New user registration')
            ->line("**{$name}** has registered and is awaiting approval.")
            ->line('Email: ' . $this->newUser->email)
            ->when($profile?->date_of_birth, fn ($m) => $m->line('Date of birth: ' . $profile->date_of_birth->format('d M Y')))
            ->when($profile?->gender, fn ($m) => $m->line('Gender: ' . ($profile->gender === 'M' ? 'Male' : 'Female')))
            ->action('Review users', url(route('filament.admin.resources.users.index')));
    }
}
