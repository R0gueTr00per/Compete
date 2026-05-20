<?php

namespace App\Notifications;

use App\Models\Enrolment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminCreatedAccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Enrolment $enrolment,
        public readonly string $resetToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $competition = $this->enrolment->competition;
        $name = $notifiable->getFilamentName();

        $events = $this->enrolment->activeEvents()
            ->with(['competitionEvent', 'division'])
            ->get()
            ->map(function ($ee) {
                $label = $ee->competitionEvent->name;
                if ($ee->division) {
                    $label .= ': ' . $ee->division->label;
                }
                return $label;
            });

        $resetUrl = \Illuminate\Support\Facades\URL::signedRoute('filament.portal.auth.password-reset.reset', [
            'token' => $this->resetToken,
            'email' => $notifiable->email,
        ]);

        $orgName = $competition->organisation?->name;
        $subject = $orgName
            ? "Welcome to {$orgName} — your account is ready"
            : "Welcome to Compete — your account is ready";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hi {$name},")
            ->line($orgName
                ? "An account has been created for you on **{$orgName}** and you have been enrolled in the following competition:"
                : "An account has been created for you on Compete and you have been enrolled in the following competition:"
            )
            ->line("**{$competition->name}**")
            ->line("**Date:** {$competition->competition_date->format('l, d F Y')}");

        if ($competition->location_name) {
            $locationLine = $competition->location_name;
            if ($competition->location_address) {
                $locationLine .= ', ' . $competition->location_address;
            }
            $message->line("**Venue:** {$locationLine}");
        }

        $message->line('**Your events:**');

        foreach ($events as $event) {
            $message->line("- {$event}");
        }

        $message->line('To set your password and access your account, click the button below.');

        return $message->action('Set your password', $resetUrl);
    }
}
