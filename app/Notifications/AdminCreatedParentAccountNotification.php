<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
use App\Models\Enrolment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminCreatedParentAccountNotification extends Notification implements ShouldQueue
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
        $childName   = $this->enrolment->competitor?->full_name ?? 'your child';
        $parentName  = $notifiable->getFilamentName();

        $events = $this->enrolment->activeEvents()
            ->with(['competitionEvent', 'division'])
            ->get();

        $resetUrl = \Illuminate\Support\Facades\URL::signedRoute('filament.portal.auth.password-reset.reset', [
            'token' => $this->resetToken,
            'email' => $notifiable->email,
        ]);

        $orgName = $org?->name;
        $subject = $orgName
            ? "Welcome to {$orgName} — {$childName} has been registered"
            : "Welcome to Compete — {$childName} has been registered";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hi {$parentName},")
            ->line($orgName
                ? "An account has been created for you on **{$orgName}** as the parent / guardian of **{$childName}**, who has been registered for the following competition:"
                : "An account has been created for you on Compete as the parent / guardian of **{$childName}**, who has been registered for the following competition:"
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

        $message->line("**{$childName}'s registered events:**");

        foreach ($events as $ee) {
            $message->line(
                "• **{$ee->competitionEvent->event_code} — {$ee->competitionEvent->name}**"
                . ($ee->division ? " / {$ee->division->label}" : '')
            );
        }

        $message
            ->line("To set your password and manage {$childName}'s registrations, click the button below.")
            ->action('Set your password', $resetUrl);

        $portalUrl = $org ? EmailFooterHelper::portalUrl($org) : '';

        return EmailFooterHelper::append($message, $org, $portalUrl);
    }
}
