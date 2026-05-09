<?php

namespace App\Notifications;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class YakusukoPartnerEnrolledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Competition $competition,
        private readonly CompetitionEvent $event,
        private readonly User $partner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Yakusuko partner confirmed — {$this->competition->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your Yakusuko partner **{$this->partner->name}** has enrolled for the same event.")
            ->line("**Competition:** {$this->competition->name}")
            ->line("**Date:** " . $this->competition->competition_date->format('l, d F Y'))
            ->line("**Event:** {$this->event->event_code} — {$this->event->name}")
            ->line("Both of you are now confirmed as Yakusuko partners for this event.")
            ->action('View my enrolments', url('/portal'))
            ->line("See you at the competition!");
    }
}
