<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
use App\Models\Competition;
use App\Models\Enrolment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Competition $competition,
        public readonly Enrolment   $enrolment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org    = $this->competition->organisation;
        $date   = \Carbon\Carbon::parse($this->competition->competition_date)->format('l, d F Y');
        $events = $this->enrolment->activeEvents()
            ->with('competitionEvent', 'division')
            ->get();

        $mail = (new MailMessage)
            ->subject("Reminder: {$this->competition->name} is in 7 days")
            ->greeting("Hi {$notifiable->name},")
            ->line("This is a reminder that **{$this->competition->name}** is on **{$date}**.");

        if ($this->competition->location_name) {
            $mail->line("**Venue:** {$this->competition->location_name}");
        }

        $checkinTime = $this->competition->competitionDays()->orderBy('date')->first()?->checkin_time;
        if ($checkinTime) {
            $mail->line('**Check-in from:** ' . \Carbon\Carbon::parse($checkinTime)->format('H:i'));
        }

        $mail->line('**Your registered events:**');

        foreach ($events as $ee) {
            $mail->line(
                "• **{$ee->competitionEvent->event_code} — {$ee->competitionEvent->name}**"
                . ($ee->division ? " / {$ee->division->label}" : '')
            );
        }

        $mail->action('View my registrations', url('/portal/account'))
            ->line('See you on the mat!');

        return EmailFooterHelper::append($mail, $org, EmailFooterHelper::portalUrl($org));
    }
}
