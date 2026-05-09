<?php
namespace App\Notifications;

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
        $date = \Carbon\Carbon::parse($this->competition->competition_date)->format('l, d F Y');
        $events = $this->enrolment->activeEvents()
            ->with('competitionEvent', 'division')
            ->get()
            ->map(fn ($ee) => $ee->competitionEvent->name
                . ($ee->division ? " ({$ee->division->label})" : ''))
            ->join(', ');

        return (new MailMessage)
            ->subject("Reminder: {$this->competition->name} is in 7 days")
            ->greeting("Hi {$notifiable->name},")
            ->line("This is a reminder that **{$this->competition->name}** is on **{$date}**.")
            ->line("Your events: {$events}")
            ->line("Check-in from: " . \Carbon\Carbon::parse($this->competition->checkin_time)->format('H:i'))
            ->line("Location: {$this->competition->location_name}")
            ->action('View my enrolments', url('/portal/my-enrolments'))
            ->line('See you on the mat!');
    }
}
