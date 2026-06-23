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

        $mail = (new MailMessage)
            ->subject("Reminder: {$this->competition->name} is in 7 days")
            ->greeting("Hi {$notifiable->name},")
            ->line("This is a reminder that **{$this->competition->name}** is on **{$date}**.")
            ->line("Your events: {$events}");

        $checkinTime = $this->competition->competitionDays()->orderBy('date')->first()?->checkin_time;
        if ($checkinTime) {
            $mail->line("Check-in from: " . \Carbon\Carbon::parse($checkinTime)->format('H:i'));
        }

        return $mail
            ->line("Location: {$this->competition->location_name}")
            ->action('View my registrations', url('/portal/account'))
            ->line('See you on the mat!');
    }
}
