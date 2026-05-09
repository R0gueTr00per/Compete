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

        $message = (new MailMessage)
            ->subject("Enrolment confirmed – {$competition->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your enrolment in **{$competition->name}** has been received.")
            ->line("**Events:** {$events}")
            ->line("**Fee:** \${$this->enrolment->fee_calculated}");

        if ($this->enrolment->is_late) {
            $message->line('_Note: a late surcharge has been applied._');
        }

        if ($competition->enrolment_due_date) {
            $message->line("**Enrolment closes:** {$competition->enrolment_due_date->format('d M Y')}");
        }

        $message->line("**Competition date:** {$competition->competition_date->format('d M Y')}");

        if ($competition->location_name) {
            $message->line("**Venue:** {$competition->location_name}");
        }

        return $message->action('View my enrolments', url('/portal'));
    }
}
