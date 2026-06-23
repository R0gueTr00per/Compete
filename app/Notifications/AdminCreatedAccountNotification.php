<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
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
        $org         = $competition->organisation;
        $name        = $notifiable->getFilamentName();

        $events = $this->enrolment->activeEvents()
            ->with(['competitionEvent', 'division'])
            ->get();

        $resetUrl = \Illuminate\Support\Facades\URL::signedRoute('filament.portal.auth.password-reset.reset', [
            'token' => $this->resetToken,
            'email' => $notifiable->email,
        ]);

        $orgName = $org?->name;
        $subject = $orgName
            ? "Welcome to {$orgName} — your account is ready"
            : 'Welcome to Compete — your account is ready';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hi {$name},")
            ->line($orgName
                ? "An account has been created for you on **{$orgName}** and you have been registered for the following competition:"
                : 'An account has been created for you on Compete and you have been registered for the following competition:'
            )
            ->line("**{$competition->name}**")
            ->line("**Date:** {$competition->competition_date->format('l, d F Y')}");

        if ($competition->location_name) {
            $location = $competition->location_name;
            if ($competition->location_address) {
                $location .= ', ' . $competition->location_address;
            }
            $message->line("**Venue:** {$location}");
        }

        $message->line('**Your registered events:**');

        foreach ($events as $ee) {
            $message->line(
                "• **{$ee->competitionEvent->event_code} — {$ee->competitionEvent->name}**"
                . ($ee->division ? " / {$ee->division->label}" : '')
            );
        }

        $message
            ->line('To set your password and access your account, click the button below.')
            ->action('Set your password', $resetUrl);

        $portalUrl = $org ? EmailFooterHelper::portalUrl($org) : '';

        return EmailFooterHelper::append($message, $org, $portalUrl);
    }
}
