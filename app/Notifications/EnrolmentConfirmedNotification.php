<?php

namespace App\Notifications;

use App\Mail\EnrolmentConfirmedMail;
use App\Models\Enrolment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EnrolmentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Enrolment $enrolment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): EnrolmentConfirmedMail
    {
        return new EnrolmentConfirmedMail($this->enrolment, $notifiable);
    }
}
