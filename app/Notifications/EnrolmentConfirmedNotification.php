<?php

namespace App\Notifications;

use App\Models\Enrolment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrolmentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Enrolment $enrolment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $competition = $this->enrolment->competition;
        $events = $this->enrolment->activeEvents()
            ->with('competitionEvent')
            ->get()
            ->map(fn ($ee) => $ee->competitionEvent->name)
            ->join(', ');

        $profileName = $this->enrolment->competitor?->full_name ?? $notifiable->getFilamentName();

        $message = (new MailMessage)
            ->subject("Registration confirmed – {$competition->name}")
            ->greeting("Hi {$notifiable->getFilamentName()},")
            ->line("Registration for **{$profileName}** in **{$competition->name}** has been received.")
            ->line("**Events:** {$events}")
            ->line("**Fee:** \${$this->enrolment->fee_calculated}");

        if ($this->enrolment->is_late) {
            $message->line('_Note: a late surcharge has been applied._');
        }

        if ($competition->enrolment_due_date) {
            $message->line("**Registration closes:** {$competition->enrolment_due_date->format('d M Y')}");
        }

        $message->line("**Competition date:** {$competition->competition_date->format('d M Y')}");

        if ($competition->location_name) {
            $message->line("**Venue:** {$competition->location_name}");
        }

        if ($this->enrolment->checkin_code) {
            $message
                ->line("**Check-in code:** `{$this->enrolment->checkin_code}`")
                ->line('Your QR code is available on the competitor portal — show it at the check-in desk for a fast scan.');
        }

        return $message->action('View my registrations & QR code', url('/portal'));
    }
}
