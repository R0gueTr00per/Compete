<?php

namespace App\Notifications;

use App\Mail\EventReminderMail;
use App\Models\Competition;
use App\Models\Enrolment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Competition $competition,
        public readonly Enrolment   $enrolment,
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): EventReminderMail
    {
        return new EventReminderMail($this->competition, $this->enrolment, $notifiable);
    }
}
